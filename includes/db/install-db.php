<?php

use Voucherly\Plugin\Constants;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

register_activation_hook( Constants::PLUGIN_PATH, [
  'Voucherly\Woocommerce\DB',
  Constants::SUFFIX_DATABASE.'install'
]);
register_deactivation_hook( Constants::PLUGIN_PATH, [
  'Voucherly\Woocommerce\DB',
  Constants::SUFFIX_DATABASE.'uninstall'
]);