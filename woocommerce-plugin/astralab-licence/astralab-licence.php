<?php
/**
 * Plugin Name: Astra Lab Licence
 * Description: Issues a licence key from manage.astralab when a WooCommerce order is paid, and shows it to the customer.
 * Version:     0.1.0
 * Author:      Astra Lab
 * Text Domain: astralab-licence
 * Requires PHP: 7.4
 *
 * The store's only job in the licensing model: when money is confirmed, ask
 * the hub for a key, store it against the order, and show it to the buyer.
 * Everything else — validation, domain binding, updates — happens between the
 * customer's install and the hub, never through here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASTRALAB_LICENCE_VERSION', '0.1.0' );
define( 'ASTRALAB_LICENCE_PATH', plugin_dir_path( __FILE__ ) );

// Order meta keys. The key itself is written once and never rewritten — see
// the note in class-orders.php about the hub only ever revealing it once.
define( 'ASTRALAB_META_KEY', '_astralab_licence_key' );
define( 'ASTRALAB_META_LAST4', '_astralab_licence_last4' );
define( 'ASTRALAB_META_ID', '_astralab_licence_id' );
define( 'ASTRALAB_META_ATTEMPTS', '_astralab_licence_attempts' );

require_once ASTRALAB_LICENCE_PATH . 'includes/class-astralab-settings.php';
require_once ASTRALAB_LICENCE_PATH . 'includes/class-astralab-client.php';
require_once ASTRALAB_LICENCE_PATH . 'includes/class-astralab-orders.php';
require_once ASTRALAB_LICENCE_PATH . 'includes/class-astralab-display.php';

/**
 * Boot only when WooCommerce is actually active — otherwise every hook below
 * targets functions that do not exist and the site white-screens.
 */
function astralab_licence_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'astralab_licence_missing_woo_notice' );
		return;
	}

	Astralab_Settings::init();
	Astralab_Orders::init();
	Astralab_Display::init();
}
add_action( 'plugins_loaded', 'astralab_licence_init' );

function astralab_licence_missing_woo_notice() {
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'Astra Lab Licence needs WooCommerce to be installed and active.', 'astralab-licence' );
	echo '</p></div>';
}

/**
 * Clear any pending retry events on deactivation, so a disabled plugin does
 * not leave orphaned cron entries firing against a hub it can no longer talk to.
 */
function astralab_licence_deactivate() {
	$timestamp = wp_next_scheduled( 'astralab_retry_issue' );
	while ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'astralab_retry_issue' );
		$timestamp = wp_next_scheduled( 'astralab_retry_issue' );
	}
}
register_deactivation_hook( __FILE__, 'astralab_licence_deactivate' );
