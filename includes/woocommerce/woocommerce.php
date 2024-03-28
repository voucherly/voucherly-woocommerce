<?php

/**
 * Listen when a status order change
 */
/*add_action('woocommerce_order_status_changed',[
    //Class\NameSpace,
    'processOrder'
],10,3);

/**
 * Listen when a new order has been added
 *
add_action('woocommerce_new_order',[
    //Class\NameSpace,
    'processOrder'
]);

/**
 * Add new GLS column to admin orders list
 *
add_filter( 'manage_edit-shop_order_columns', [
    //Class\NameSpace,
    'addColumnOrdersList'
],20);

/** 
 * Renders GLS state on admin orders column
 *
add_action( 'manage_shop_order_posts_custom_column' , [
    //Class\NameSpace,
    'getGLSColumnValue'
], 20, 2 );*/

/**
 * Add box inside admin order
 */
add_action( 'add_meta_boxes', [
    '\Voucherly\Woocommerce\AdminOrder',
    'showAdminOrderBox'
]);

add_action( 'init' , [
    '\Voucherly\Woocommerce\Payment\Router',
    'addRoutes'
]);

add_filter( 'template_include' , [
    '\Voucherly\Woocommerce\Payment\Router',
    'renderTemplate'
]);

add_action('woocommerce_order_status_changed',[
    '\Voucherly\Woocommerce\Payment\Gateway',
    'refund'
], 10, 4);

add_action('woocommerce_before_checkout_form',[
    '\Voucherly\Woocommerce\Payment\Gateway',
    'error'
]);

add_action( 'woocommerce_payment_gateways' , [
    '\Voucherly\Woocommerce\Payment\Gateway',
    'getPaymentMethod'
], 20, 2 );