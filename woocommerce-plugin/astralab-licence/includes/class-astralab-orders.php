<?php
/**
 * Turns a paid order into licence keys.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Astralab_Orders {

	/** Statuses that mean the money is real. */
	const PAID_STATUSES = array( 'processing', 'completed' );

	/** Give up after this many attempts and leave it for a human. */
	const MAX_ATTEMPTS = 5;

	public static function init() {
		// Covers both gateway callbacks and an admin marking an order paid by
		// hand. The guard inside maybe_issue() makes firing twice harmless.
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_issue' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_issue' ), 10, 1 );
		add_action( 'astralab_retry_issue', array( __CLASS__, 'maybe_issue' ), 10, 1 );
	}

	/**
	 * Issue a licence for every licensed line item on the order.
	 *
	 * @param int $order_id
	 */
	public static function maybe_issue( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( ! in_array( $order->get_status(), self::PAID_STATUSES, true ) ) {
			return;
		}

		$issued_any = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$slug = Astralab_Settings::product_slug( $product );
			if ( empty( $slug ) ) {
				continue; // Not a licensed product — ordinary merchandise.
			}

			if ( self::key_for_item( $order, $item_id ) ) {
				continue; // Already issued. Never ask twice.
			}

			$issued_any = self::issue_for_item( $order, $item_id, $slug ) || $issued_any;
		}

		if ( $issued_any ) {
			$order->save();
		}
	}

	/**
	 * @return bool whether anything was written to the order.
	 */
	private static function issue_for_item( $order, $item_id, $slug ) {
		// One reference per line item, not per order: an order containing two
		// licensed products must yield two distinct licences, and the hub
		// deduplicates on exactly this string.
		$order_ref = $order->get_id() . '-' . $item_id;

		$item = $order->get_item( $item_id );

		$payload = array(
			'productSlug'   => $slug,
			'orderRef'      => (string) $order_ref,
			'customerEmail' => $order->get_billing_email(),
			// What this line actually charged, so the hub reports revenue from
			// real orders rather than multiplying a licence count by today's
			// price — which would rewrite past months on every price change.
			'amount'        => $item ? (float) $order->get_line_total( $item, true ) : null,
			'currency'      => $order->get_currency(),
		);

		// Omit rather than send an empty string — billing names are not always
		// filled in, and the hub treats the field as absent-or-a-real-name.
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		if ( '' !== $name ) {
			$payload['customerName'] = $name;
		}

		$result = Astralab_Client::issue_licence( $payload );

		if ( is_wp_error( $result ) ) {
			self::handle_failure( $order, $item_id, $result );
			return true; // Attempt counter changed, so the order still needs saving.
		}

		// The hub hands over the plaintext key exactly once. Persist it before
		// doing anything else that could fail — a notice, an email, a hook —
		// because if this write is lost the key is unrecoverable and the order
		// has to be refunded or reissued by hand.
		if ( ! empty( $result['licenceKey'] ) ) {
			$order->update_meta_data( ASTRALAB_META_KEY . '_' . $item_id, $result['licenceKey'] );
			$order->save_meta_data();
		}

		if ( ! empty( $result['keyLast4'] ) ) {
			$order->update_meta_data( ASTRALAB_META_LAST4 . '_' . $item_id, $result['keyLast4'] );
		}
		if ( ! empty( $result['licenceId'] ) ) {
			$order->update_meta_data( ASTRALAB_META_ID . '_' . $item_id, $result['licenceId'] );
		}
		$order->delete_meta_data( ASTRALAB_META_ATTEMPTS . '_' . $item_id );

		if ( ! empty( $result['duplicate'] ) ) {
			// The hub already had this order reference. It withholds the key on
			// repeat calls by design, so there is nothing to recover here — say
			// so plainly rather than leaving a confusing blank.
			$order->add_order_note(
				sprintf(
					/* translators: %s: last four characters of the licence key */
					__( 'Astra Lab: licence already existed for this item (ending %s). The key is not retrievable — resend it from the hub if the customer lost it.', 'astralab-licence' ),
					isset( $result['keyLast4'] ) ? $result['keyLast4'] : '????'
				)
			);
		} else {
			$order->add_order_note( __( 'Astra Lab: licence issued and attached to this order.', 'astralab-licence' ) );
		}

		return true;
	}

	/**
	 * Record a failed attempt and schedule a retry.
	 *
	 * A payment has already succeeded at this point, so giving up silently
	 * would mean a customer who paid and received nothing. Retries back off,
	 * and the final failure leaves a loud order note for a human.
	 */
	private static function handle_failure( $order, $item_id, WP_Error $error ) {
		$meta     = ASTRALAB_META_ATTEMPTS . '_' . $item_id;
		$attempts = (int) $order->get_meta( $meta ) + 1;
		$order->update_meta_data( $meta, $attempts );

		$order->add_order_note(
			sprintf(
				/* translators: 1: attempt number, 2: maximum attempts, 3: error message */
				__( 'Astra Lab: licence issue failed (attempt %1$d of %2$d) — %3$s', 'astralab-licence' ),
				$attempts,
				self::MAX_ATTEMPTS,
				$error->get_error_message()
			)
		);

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$order->add_order_note(
				__( 'Astra Lab: giving up after repeated failures. This paid order has NO licence — issue one manually from the hub.', 'astralab-licence' )
			);
			return;
		}

		// 5min, 20min, 45min, 80min — long enough for a brief hub outage or a
		// deploy to finish without hammering it.
		$delay = 300 * $attempts * $attempts;
		wp_schedule_single_event( time() + $delay, 'astralab_retry_issue', array( $order->get_id() ) );
	}

	/** The plaintext key stored for one line item, if any. */
	public static function key_for_item( $order, $item_id ) {
		return $order->get_meta( ASTRALAB_META_KEY . '_' . $item_id );
	}

	/**
	 * All licence keys on an order, keyed by product name — what the customer
	 * actually needs to see.
	 *
	 * @return array<string,string>
	 */
	public static function keys_for_order( $order ) {
		$keys = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$key = self::key_for_item( $order, $item_id );
			if ( $key ) {
				$keys[ $item->get_name() ] = $key;
			}
		}
		return $keys;
	}
}
