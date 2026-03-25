<?php

/**
 * Migration 1.2.0.
 *
 * Rename user meta keys from 'live'/'sand' to 'voucherly_customer_live'/'voucherly_customer_sand'.
 */
defined('ABSPATH') || exit;

function voucherly_migrate_1_2_0_up()
{
    global $wpdb;

    $wpdb->query(
        "UPDATE {$wpdb->usermeta}
        SET meta_key = 'voucherly_customer_live'
        WHERE meta_key = 'live'"
    );

    $wpdb->query(
        "UPDATE {$wpdb->usermeta}
        SET meta_key = 'voucherly_customer_sand'
        WHERE meta_key = 'sand'"
    );
}

function voucherly_migrate_1_2_0_down()
{
    global $wpdb;

    $wpdb->query(
        "UPDATE {$wpdb->usermeta}
        SET meta_key = 'live'
        WHERE meta_key = 'voucherly_customer_live'"
    );

    $wpdb->query(
        "UPDATE {$wpdb->usermeta}
        SET meta_key = 'sand'
        WHERE meta_key = 'voucherly_customer_sand'"
    );
}
