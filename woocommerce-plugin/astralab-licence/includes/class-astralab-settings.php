<?php
/**
 * Settings: where the hub is, how we authenticate to it, and which products
 * actually carry a licence.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Astralab_Settings {

	const OPT_HUB    = 'astralab_hub_url';
	const OPT_SECRET = 'astralab_api_secret';
	const OPT_DOCS   = 'astralab_docs_url';

	/** Product meta naming the hub-side product, e.g. "astralab-cms". */
	const PRODUCT_META = '_astralab_product_slug';

	public static function init() {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_field' ) );
	}

	public static function hub_url() {
		return untrailingslashit( (string) get_option( self::OPT_HUB, '' ) );
	}

	public static function api_secret() {
		return (string) get_option( self::OPT_SECRET, '' );
	}

	public static function docs_url() {
		return (string) get_option( self::OPT_DOCS, '' );
	}

	/**
	 * The hub-side product slug for a WooCommerce product, or '' if this
	 * product does not carry a licence.
	 *
	 * Variations inherit from their parent — a licence does not differ by
	 * variation, and asking the shop owner to set it on every one invites the
	 * mistake of missing one.
	 */
	public static function product_slug( $product ) {
		$id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$slug = get_post_meta( $id, self::PRODUCT_META, true );
		return is_string( $slug ) ? trim( $slug ) : '';
	}

	public static function register_page( $pages ) {
		require_once ASTRALAB_LICENCE_PATH . 'includes/class-astralab-settings-page.php';
		$pages[] = new Astralab_Settings_Page();
		return $pages;
	}

	/** "Astra Lab product slug" field on the product edit screen. */
	public static function product_field() {
		woocommerce_wp_text_input(
			array(
				'id'          => self::PRODUCT_META,
				'label'       => __( 'Astra Lab product slug', 'astralab-licence' ),
				'description' => __( 'Leave empty for ordinary products. Set to the hub product key (e.g. astralab-cms) to issue a licence when this is bought.', 'astralab-licence' ),
				'desc_tip'    => true,
				'placeholder' => 'astralab-cms',
			)
		);
	}

	public static function save_product_field( $post_id ) {
		// Nonce and capability are already verified by WooCommerce before this
		// hook runs; sanitise anyway because the value reaches an API call.
		$raw = isset( $_POST[ self::PRODUCT_META ] ) ? wp_unslash( $_POST[ self::PRODUCT_META ] ) : '';
		$val = sanitize_key( $raw );
		if ( '' === $val ) {
			delete_post_meta( $post_id, self::PRODUCT_META );
		} else {
			update_post_meta( $post_id, self::PRODUCT_META, $val );
		}
	}
}
