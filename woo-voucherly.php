<?php
/**
 * Plugin Name: Voucherly
 * Description: Accetta pagamenti tramite buoni pasto per il tuo ecommerce. Non perdere neanche una vendita, incassa online in totale sicurezza e in qualsiasi modalità. Il modo migliore per usare i buoni pasto!
 * Author: Voucherly
 * Author URI: https://voucherly.it/
 * Version: 1.0.0
 * WC tested up to: 8.6.1
 */

use Voucherly\Plugin\Constants;

define("CACHE_BUSTER", time() );
require_once('vendor/autoload.php');
require_once(Constants::PLUGIN_FOLDER_PATH.'includes/ui/admin.php');

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
add_action('plugins_loaded', 'wc_voucherly_init');
add_filter('cron_schedules', 'wc_voucherly_cron_schedule');

function wc_voucherly_init() {

	include_once('wc-voucherly.php');
    
	if (!class_exists('WC_Voucherly')) return;

    // Make the Voucherly gateway available to WC.
	add_action('woocommerce_payment_gateways', 'wc_voucherly_add_gateway');
    function wc_voucherly_add_gateway($methods) {
        $methods[] = "WC_Voucherly";
        return $methods;
    }

    // Registers WooCommerce Blocks integration.
    add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_voucherly_woocommerce_block_support');
    function woocommerce_gateway_voucherly_woocommerce_block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType') ) {
            require_once 'includes/blocks/wc-voucherly-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Voucherly_Blocks );
                }
            );
        }
    }

	add_filter('plugin_action_links_'.plugin_basename( __FILE__ ), 'wc_voucherly_action_links');
	function wc_voucherly_action_links($links) {
		$pluginLinks = array(
			'<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=voucherly').'">'.__('Settings', 'woo-voucherly').'</a>'
		);
		return array_merge($pluginLinks, $links);
	}

    add_action('wc_voucherly_finalize_orders_event', 'wc_voucherly_finalize_orders');
    function wc_voucherly_finalize_orders()
    {
        $model = new WC_Voucherly();
        $model->finalize_orders();
    }
}

/**
 * Add more cron schedules.
 *
 * @param array $schedules List of WP scheduled cron jobs.
 *
 * @return array
 */
function wc_voucherly_cron_schedule($schedules) {
    $schedules['every_four_hours'] = array(
        'interval' => 14400, // Every 4 hours
        'display'  => __( 'Every 4 hours' ),
    );
    return $schedules;
}

function wc_voucherly_activate()
{
    if ( !wp_next_scheduled( 'wc_voucherly_finalize_orders_event' ) ) {
        wp_schedule_event(time(), 'every_four_hours', 'wc_voucherly_finalize_orders_event'); // wc_voucherly_finalize_orders_event is a hook
    }
}
register_activation_hook( __FILE__, 'wc_voucherly_activate');

function wc_voucherly_deactivate()
{
    wp_clear_scheduled_hook('wc_voucherly_finalize_orders_event');
}
register_deactivation_hook( __FILE__ , 'wc_voucherly_deactivate');
