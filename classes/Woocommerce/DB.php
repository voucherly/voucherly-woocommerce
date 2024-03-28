<?php

namespace Voucherly\Woocommerce;

use Voucherly\Plugin\Constants;

/**
 * Handles database migrations/seeding/dopping
 */
class DB{
  /**
   * Executed when installing the plugin
   */
  public static function voucherly_install()
  {
    global $wpdb;
    
    $sql = [
      "CREATE TABLE {$wpdb->prefix}".Constants::SUFFIX_DATABASE."orders  (
        `id_order` bigint(20) NOT NULL DEFAULT '0',
        `id_user` bigint(20) NOT NULL DEFAULT 0,
        `environment` tinyint(4) NOT NULL DEFAULT 0,
        `transaction_id` varchar(30) NOT NULL DEFAULT '',
        `customer_id` varchar(30) NOT NULL DEFAULT '',
        `details` text NOT NULL DEFAULT '',
        `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_upd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `id_order` (`id_order`),
        UNIQUE KEY `transaction_id` (`transaction_id`),
        KEY `customer_id` (`customer_id`),
        KEY `id_user` (`id_user`),
        KEY `environment` (`environment`)
      ) {$wpdb->get_charset_collate()};"
    ];
    
    dbDelta(implode("",$sql));
    add_option(Constants::SUFFIX_DATABASE.'_db_version', '1.0');
  }

  /**
   * Executed when uninstalling the plugin
   */
  public static function voucherly_uninstall()
  {
    global $wpdb;

    $wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix.Constants::SUFFIX_DATABASE."orders");
  }
}