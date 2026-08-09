<?php
/**
 * Shows licence keys to the customer.
 *
 * Three places, because a key the buyer cannot find is a support ticket:
 * the thank-you page immediately after paying, the order in My Account
 * forever after, and the order confirmation email.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Astralab_Display {

	public static function init() {
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'on_thankyou' ), 20 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'on_order_details' ), 20 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'on_email' ), 20, 4 );
	}

	public static function on_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::render( $order, false );
		}
	}

	public static function on_order_details( $order ) {
		self::render( $order, false );
	}

	/**
	 * Only in the customer's own completed/processing emails — never in an
	 * admin notification, which would put live keys in the shop owner's inbox
	 * for no reason.
	 */
	public static function on_email( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( $sent_to_admin ) {
			return;
		}
		self::render( $order, $plain_text );
	}

	private static function render( $order, $plain_text ) {
		$keys = Astralab_Orders::keys_for_order( $order );
		if ( empty( $keys ) ) {
			return;
		}

		$docs = Astralab_Settings::docs_url();

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'YOUR LICENCE KEY(S)', 'astralab-licence' ) . "\n";
			foreach ( $keys as $name => $key ) {
				echo esc_html( $name ) . ': ' . esc_html( $key ) . "\n";
			}
			echo esc_html__( 'Enter this when you run the installer. One key activates one production domain.', 'astralab-licence' ) . "\n";
			if ( $docs ) {
				echo esc_html__( 'Installation guide: ', 'astralab-licence' ) . esc_url( $docs ) . "\n";
			}
			return;
		}

		echo '<section class="astralab-licences" style="margin:24px 0;padding:16px;border:1px solid #dcdcde;border-radius:8px;">';
		echo '<h2 style="margin-top:0;font-size:1.05em;">' . esc_html__( 'Your licence key', 'astralab-licence' ) . '</h2>';

		foreach ( $keys as $name => $key ) {
			echo '<p style="margin:8px 0;">';
			echo '<strong>' . esc_html( $name ) . '</strong><br>';
			// Monospace and selectable — this gets copied by hand into an
			// installer field, so it has to be unambiguous to read.
			echo '<code style="display:inline-block;margin-top:4px;padding:8px 12px;background:#f6f7f7;border-radius:4px;font-size:1.1em;letter-spacing:0.05em;">';
			echo esc_html( $key );
			echo '</code>';
			echo '</p>';
		}

		echo '<p style="margin-bottom:0;color:#50575e;font-size:0.92em;">';
		esc_html_e( 'Enter this when you run the installer. One key activates one production domain — you can move it to another domain later by deactivating it first.', 'astralab-licence' );
		if ( $docs ) {
			echo ' <a href="' . esc_url( $docs ) . '">' . esc_html__( 'Installation guide', 'astralab-licence' ) . '</a>';
		}
		echo '</p>';
		echo '</section>';
	}
}
