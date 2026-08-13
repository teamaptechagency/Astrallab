<?php
/**
 * WooCommerce → Settings → Astra Lab.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Settings_Page' ) ) {
	return;
}

class Astralab_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'astralab';
		$this->label = __( 'Astra Lab', 'astralab-licence' );
		parent::__construct();
	}

	public function get_settings() {
		return array(
			array(
				'title' => __( 'Licence hub', 'astralab-licence' ),
				'type'  => 'title',
				'desc'  => __( 'Where this store asks for licence keys when an order is paid.', 'astralab-licence' ),
				'id'    => 'astralab_section',
			),
			array(
				'title'    => __( 'Hub URL', 'astralab-licence' ),
				'id'       => Astralab_Settings::OPT_HUB,
				'type'     => 'url',
				'placeholder' => 'https://manage.astrallabs.uk',
				'desc'     => __( 'No trailing slash. Must be HTTPS in production — the licence key comes back in this response.', 'astralab-licence' ),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'API secret', 'astralab-licence' ),
				'id'       => Astralab_Settings::OPT_SECRET,
				// password type keeps it out of plain sight in the admin, though
				// it is stored in wp_options like any other setting.
				'type'     => 'password',
				'desc'     => __( 'Must match STORE_API_SECRET on the hub. Requests are rejected if it does not.', 'astralab-licence' ),
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Installation guide URL', 'astralab-licence' ),
				'id'       => Astralab_Settings::OPT_DOCS,
				'type'     => 'url',
				'desc'     => __( 'Linked next to the licence key on the thank-you page and in emails.', 'astralab-licence' ),
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'astralab_section',
			),
		);
	}
}
