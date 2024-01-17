<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Vipps Recurring payment method integration for Gutenberg Blocks
 *
 * @since 2.6.0
 */
final class WC_Vipps_Recurring_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'vipps_recurring';

	protected WC_Gateway_Vipps_Recurring $gateway;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = \WC_Vipps_Recurring_Helper::get_settings();
		$this->gateway  = WC_Gateway_Vipps_Recurring::get_instance();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$version      = filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . "/assets/js/vipps-recurring-payment-method-block.js" );
		$path         = WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/js/vipps-recurring-payment-method-block.js';
		$handle       = 'wc-payment-method-vipps_recurring';
		$dependencies = [ 'wp-hooks', 'wp-i18n' ];

		wp_register_script( $handle, $path, $dependencies, $version, true );

//		if ( function_exists( 'wp_set_script_translations' ) ) {
//			wp_set_script_translations( $handle, 'woo-vipps-recurring', WC_VIPPS_RECURRING_PLUGIN_PATH . '/languages/' );
//		}

		return [ $handle ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'logo'        => apply_filters( 'woo_vipps_recurring_checkout_logo_url', WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/vipps-mark.svg' ),
			'supports'    => $this->get_supported_features(),
		];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways[ $this->name ] ) ) {
			$gateway = $gateways[ $this->name ];

			return array_filter( $gateway->supports, [ $gateway, 'supports' ] );
		}

		return [];
	}
}