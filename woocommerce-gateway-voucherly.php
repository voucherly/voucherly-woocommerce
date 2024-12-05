<?php

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Plugin Name: Voucherly
 * Plugin URI: https://voucherly.it/
 * Description: Accetta buoni pasto con il tuo ecommerce. Non perdere neanche una vendita, incassa online in totale sicurezza e in qualsiasi modalità. Il modo migliore per usare i buoni pasto!
 * Author: Voucherly
 * Author URI: voucherly.it
 * Version: 1.1.0
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.6.0
 * Text Domain: voucherly
 * Domain Path: /i18n/languages/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html.
 */
defined('ABSPATH') || exit;

add_action('before_woocommerce_init', static function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('plugins_loaded', 'voucherly_init', 0);
add_filter('cron_schedules', 'voucherly_cron_schedule');

function voucherly_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once 'voucherly.php';

    // Make the Voucherly gateway available to WC.
    add_filter('woocommerce_payment_gateways', 'voucherly_add_gateway', 15);
    function voucherly_add_gateway($methods)
    {
        $methods[] = 'Voucherly';

        return $methods;
    }

    // Registers WooCommerce Blocks integration.
    add_action('woocommerce_blocks_loaded', 'woocommerce_gateway_voucherly_woocommerce_block_support');
    function woocommerce_gateway_voucherly_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once 'includes/blocks/voucherly-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                static function (PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new Voucherly_Blocks());
                }
            );
        }
    }

    add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'voucherly_action_links');
    function voucherly_action_links($links)
    {
        $pluginLinks = [
            '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=voucherly').'">'.__('Settings', 'voucherly').'</a>',
        ];

        return array_merge($pluginLinks, $links);
    }

    add_action('voucherly_finalize_orders_event', 'voucherly_finalize_orders');
    function voucherly_finalize_orders()
    {
        $model = new Voucherly();
        $model->finalize_orders();
    }

    add_action('voucherly_update_payment_gateways_event', 'voucherly_update_payment_gateways');
    function voucherly_update_payment_gateways()
    {
        $model = new Voucherly();
        $model->update_payment_gateways();
    }
}

/**
 * Add more cron schedules.
 *
 * @param array $schedules list of WP scheduled cron jobs
 *
 * @return array
 */
function voucherly_cron_schedule($schedules)
{
    $schedules['every_four_hours'] = [
        'interval' => 14400,
        'display' => 'Every 4 hours',
    ];
    $schedules['every_day'] = [
        'interval' => 86400,
        'display' => 'Every day',
    ];

    return $schedules;
}

function voucherly_activate()
{
    if (!wp_next_scheduled('voucherly_finalize_orders_event')) {
        wp_schedule_event(time(), 'every_four_hours', 'voucherly_finalize_orders_event'); // voucherly_finalize_orders_event is a hook
    }
    if (!wp_next_scheduled('voucherly_update_payment_gateways_event')) {
        wp_schedule_event(time(), 'every_day', 'voucherly_update_payment_gateways_event'); // voucherly_update_payment_gateways_event is a hook
    }
}
register_activation_hook(__FILE__, 'voucherly_activate');

function voucherly_deactivate()
{
    wp_clear_scheduled_hook('voucherly_finalize_orders_event');
    wp_clear_scheduled_hook('voucherly_update_payment_gateways_event');
}
register_deactivation_hook(__FILE__, 'voucherly_deactivate');
