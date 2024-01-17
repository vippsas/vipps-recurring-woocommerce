<?php

defined( 'ABSPATH' ) || exit;

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'                          => [
			'title'       => __( 'Enable/Disable', 'woo-vipps-recurring' ),
			'label'       => __( 'Enable Vipps Recurring Payments', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'branding'                         => [
			'title'       => __( 'Branding', 'woo-vipps-recurring' ),
			'type'        => 'select',
			'description' => __( 'Controls the payment flow branding (Vipps or MobilePay).', 'woo-vipps-recurring' ),
			'default'     => 'vipps',
			'options'     => [
				'vipps'     => 'Vipps',
				'mobilepay' => 'MobilePay'
			]
		],
		'description'                      => [
			'title'       => __( 'Description', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Pay with Vipps.', 'woo-vipps-recurring' ),
		],
		'merchant_serial_number'           => [
			'title'       => __( 'Merchant Serial Number (MSN)', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant Serial Number your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'client_id'                        => [
			'title'       => __( 'client_id', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                       => [
			'title'       => __( 'client_secret', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'                 => [
			'title'       => __( 'Ocp-Apim-Subscription-Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps MobilePay developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'cancelled_order_page'             => [
			'type'             => 'page_dropdown',
			'title'            => __( 'Cancelled order redirect page', 'woo-vipps-recurrinsg' ),
			'description'      => __( 'The page to redirect cancelled orders to.', 'woo-vipps-recurring' ),
			'show_option_none' => __( 'Create a new page', 'woo-vipps-recurring' )
		],
		'default_reserved_charge_status'   => [
			'type'        => 'select',
			'title'       => __( 'Default status to give orders with a reserved charge', 'woo-vipps-recurring' ),
			'description' => __( 'The status to give orders when the charge is reserved in Vipps (i.e. tangible goods). Notice: This option only counts for newly signed agreements by the customer. Use the setting below to set the default status for renewal orders.', 'woo-vipps-recurring' ),
			'default'     => 'wc-on-hold',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'default_renewal_status'           => [
			'type'        => 'select',
			'title'       => __( 'Default status to give pending renewal orders', 'woo-vipps-recurring' ),
			'description' => __( 'When a renewal order happens we have to wait a few days for the money to be drawn from the customer. This settings controls the status to give these renewal orders before the charge completes.', 'woo-vipps-recurring' ),
			'default'     => 'wc-processing',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'transition_renewals_to_completed' => [
			'type'        => 'checkbox',
			'title'       => __( 'Transition order status for renewals to "completed"', 'woo-vipps-recurring' ),
			'label'       => __( 'Transition order status for renewals to "completed"', 'woo-vipps-recurring' ),
			'description' => __( 'This option will make sure order statuses always transition to "completed" when the renewal charge is completed in Vipps.', 'woo-vipps-recurring' ),
			'default'     => 'no',
		],
		'check_charges_amount'             => [
			'type'        => 'number',
			'title'       => __( 'Amount of charges to check per status check', 'woo-vipps-recurring' ),
			'description' => __( 'The amount of charges to check the status for in wp-cron per scheduled event. It is recommended to keep this between 5 and 100. The higher the value, the more performance issues you may run into.', 'woo-vipps-recurring' ),
			'default'     => 10,
		],
		'check_charges_sort_order'         => [
			'type'        => 'select',
			'title'       => __( 'Status checking sort order for charges', 'woo-vipps-recurring' ),
			'description' => __( 'The sort order we use when checking charges in wp-cron. Random sort order is the best for most use cases. Oldest first may be useful if you use synchronized renewals.', 'woo-vipps-recurring' ),
			'default'     => 'rand',
			'options'     => [
				'rand' => __( 'Random', 'woo-vipps-recurring' ),
				'asc'  => __( 'Oldest first', 'woo-vipps-recurring' ),
				'desc' => __( 'Newest first', 'woo-vipps-recurring' )
			]
		],
		'logging'                          => [
			'title'       => __( 'Logging', 'woo-vipps-recurring' ),
			'label'       => __( 'Log debug messages', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woo-vipps-recurring' ),
			'default'     => 'yes',
		],
	]
);
