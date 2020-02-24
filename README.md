# Vipps Recurring Payments for WooCommerce

**This plugin is currently a test pilot (pre-release). It is very likely that you will encounter bugs or scenarios that are not yet supported.**

**We encourage you to create an issue here or on the WordPress plugin page if you require assistance or run in to a problem.**

For Vipps contact information check the main Vipps GitHub page: [https://github.com/vippsas](https://github.com/vippsas)

# Description

This is the official Vipps Recurring Payments plugin for WooCommerce. It is owned by Vipps AS and maintained by Everyday AS.

Vipps Recurring Payments for WooCommerce is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps Recurring Payments for WooCommerce you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

# Table of contents

* [ Requirements ](#requirements)
* [ Getting started ](#getting-started)
  * [ Installation ](#installation)
  * [ Retrieving Vipps API keys ](#retrieving-vipps-api-keys)
  * [ Configuration of the plugin ](#configuration-of-the-plugin)
  * [ Configuring products ](#configuring-products)
* [ Extending the plugin ](#extending-the-plugin)
  * [ Constants ](#constants)
* [ Frequently Asked Questions ](#frequently-asked-questions)

# Requirements

* WooCommerce 3.3.4 or newer
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
* PHP 7.1 or higher
* An SSL certificate must be installed and configured
* Port 443 must not be blocked for outgoing traffic

# Getting started

* Sign up to use ([Vipps på Nett](https://www.vipps.no/signup/vippspanett/))
* After 1-2 days you will get an email with login details to Vipps Developer Portal. This is where you can retrieve the API credentials used to configure the plugin in WooCommerce.
* Proceed to "Installation" below

## Installation

1. Download and activate the plugin from this GitHub repository or Wordpress.org
2. Enable the Vipps Recurring Payments ("Vipps faste betalinger") payment method in WooCommerce -> Settings -> Payments (Betalinger).
3. Click "Manage" on the Vipps Recurring Payments payment method
4. Proceed to "Retrieving Vipps API Keys" below

## Retrieving Vipps API Keys

1. Sign in to the Vipps Portal at [https://portal.vipps.no/](https://portal.vipps.no/) using Bank ID
2. Select the "Utvikler" ("Developer") tab and choose Production Keys
3. Click on "Show keys" under the API keys column to see "Client ID", "Client Secret" and "Subscription Key"
4. Proceed to "Configuration of the plugin" below

## Configuration of the plugin

1. Fill in the `Client ID`, `Secret Key` and `Subscription Key` found in the previous step. You should fill the fields with the prefix "Live" if you are using the production keys.
2. That's it! You can now move on to "Configuring products"

## Configuring products

Configuring products for use with the Vipps Recurring Payments plugin is not any different from default WooCommerce, with one exception.

The configuration for whether or not the product is virtual or physical is important to consider. 
If a product is virtual the customer will be charged immediately but if the product is not virtual you will have to capture the payment manually through the order in WooCommerce when you have shipped the product.

In most cases your products should be virtual when using subscriptions but it is possible to use the plugin with physical products if you need to do so.

# Extending the plugin

WooCommerce and WooCommerce Subscriptions has a lot of [default actions](https://docs.woocommerce.com/document/subscriptions/develop/action-reference/) that interact with the payment flow so there should not be any need to extend this plugin directly, 
but if you need an action or filter added to the plugin don't hesitate to create an issue on GitHub and we will look into this as soon as possible.

The plugin is currently in a pre-release phase and will have more filters, actions and features further down the road.

## Constants

Constants can be re-defined by using `define('CONSTANT_NAME', 'value');` in `wp-config.php`.

`WC_VIPPS_RECURRING_RETRY_DAYS`: (integer) default: 3

The amount of days Vipps will retry a charge for before it fails. Documentation can be found [here](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api.md#charge-retries).

`WC_VIPPS_RECURRING_DUE_MINIMUM_DAYS`: (integer) default: 6

When a charge is created it will be the status of `DUE` in the Vipps API for 6 days, this value makes it so the plugin creates the charge 6 days before the due date.

Typically you do not want to edit this value, but if you want the charge to be created at the billing date you should change this constant to 0.

# Frequently Asked Questions

## Does this plugin work alongside the Vipps for WooCommerce plugin?

Yes! You can use both plugins at the same time alongside each other.

## Do I need to have a license for WooCommerce Subscriptions in order to use this plugin?

Yes, you do.

## Does this plugin work with the WooCommerce Memberships-plugin?

No, it's for WooCommerce Subscriptions only.

You can however use both WooCommerce Subscriptions and WooCommerce Memberships at the same time as explained [here](https://docs.woocommerce.com/document/woocommerce-memberships-subscriptions-integration/).

## How can I get help if I have any issues?

For issues with the plugin you can submit an issue on GitHub or ask on the support forum on wordpress.org. For other unrelated issues you should [contact Vipps](https://github.com/vippsas/vipps-developers/blob/master/contact.md).

## Where can I use Vipps?

Vipps is only available in Norway at the moment and only users who have Vipps will be able to pay with Vipps.

## How can I test that the plugin works correctly?

If you have access to the Vipps test environment you are able to use the test mode found in the plugin settings. 
See the [getting started](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md) guide for details about how to get started using the test environment.

Vipps does not offer test accounts for regular users of the plugin but you can still penny-test the plugin by sending a small amount of money like 1 or 2 NOK using your production keys. 
You can then refund or cancel the purchase afterwards.


