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
require_once(Constants::PLUGIN_FOLDER_PATH.'includes/woocommerce/woocommerce.php');
require_once(Constants::PLUGIN_FOLDER_PATH.'includes/api/routes.php');
require_once(Constants::PLUGIN_FOLDER_PATH.'includes/db/install-db.php');