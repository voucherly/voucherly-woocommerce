<?php
/**
 * Plugin Name: Voucherly
 * Description: Voucherly
 * Version: 1.0
 * Author: Voucherly
 * Author URI: https://voucherly.it/
 */

use Voucherly\Plugin\Constants;

define("CACHE_BUSTER",time());
require_once('vendor/autoload.php');
require_once(Constants::PLUGIN_FOLDER_PATH.'includes/ui/admin.php');



add_action('plugins_loaded', 'wc_voucherly_init');

function wc_voucherly_init() {

	include_once('wc-voucherly.php');
    
	if (!class_exists('WC_Payment_Voucherly')) return;

    // Make the Voucherly gateway available to WC.
	add_action('woocommerce_payment_gateways', 'wc_voucherly_add_gateway');
    function wc_voucherly_add_gateway($methods) {
        $methods[] = "WC_Payment_Voucherly";
        return $methods;
    }

    // // Registers WooCommerce Blocks integration.
    // add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_satispay_woocommerce_block_support');
    // function woocommerce_gateway_satispay_woocommerce_block_support() {
    //     if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType') ) {
    //         require_once 'includes/blocks/wc-satispay-blocks.php';
    //         add_action(
    //             'woocommerce_blocks_payment_method_type_registration',
    //             function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
    //                 $payment_method_registry->register( new WC_Satispay_Blocks );
    //             }
    //         );
    //     }
    // }

	// add_filter('plugin_action_links_'.plugin_basename( __FILE__ ), 'wc_satispay_action_links');
	// function wc_satispay_action_links($links) {
	// 	$pluginLinks = array(
	// 		'<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=satispay').'">'.__('Settings', 'woo-satispay').'</a>'
	// 	);
	// 	return array_merge($pluginLinks, $links);
	// }
    // add_action('wc_satispay_finalize_orders_event', 'wc_satispay_finalize_orders');
}