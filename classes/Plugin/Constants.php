<?php

namespace Voucherly\Plugin;

/**
 * Constants
 */
class Constants{

  /**
   * Plugin constants
   */
  const PLUGIN_NAME = "Voucherly";
  const SUFFIX_DATABASE = "voucherly_";
  const PLUGIN_FOLDER_NAME = "voucherly";
  const DOMAIN = self::PLUGIN_FOLDER_NAME;
  const WEB_PLUGIN_PATH = '/wp-content/plugins/'.self::PLUGIN_FOLDER_NAME.'/';
  const PLUGIN_PATH = ABSPATH.self::WEB_PLUGIN_PATH.self::PLUGIN_FOLDER_NAME.'.php';
  const PLUGIN_FOLDER_PATH = ABSPATH.'wp-content/plugins/'.self::PLUGIN_FOLDER_NAME.'/';
  const LOG_FOLDER = self::PLUGIN_FOLDER_PATH.'log/';
  const LOG_WEB_FOLDER = self::WEB_PLUGIN_PATH.'log/';
  const _SHORTCODE_FOLDER = self::PLUGIN_FOLDER_PATH.'ui/view/shortcode/';
  const CSS_ASSETS = self::WEB_PLUGIN_PATH.'includes/ui/assets/css/';
  const JS_ASSETS = self::WEB_PLUGIN_PATH.'includes/ui/assets/js/';
  const IMG_ASSETS = self::WEB_PLUGIN_PATH.'includes/ui/assets/img/';


  /**
   * Admin constants
   */

  const MAP_ORDERS = 'map_orders';
  const MAP_CARRIERS = 'map_carriers';
  const LIVE_API = 'live_api';
  const CATEGORY_IS_FOOD = 'category_is_food';
  const LOG = 'log';
  const API_KEY = 'api_key';
  const API_KEY_SAND = 'api_key_sand';
  const EMAIL_NOTIFICATIONS = 'email_notiifcations';
  const REFUND_TYPE = 'refund_type';
  const WS_KEY_SECRET = 'ws_key_secret';
  const WS_KEY = 'ws_key';
  const READ_SCOPE = 'read';
  const WRITE_SCOPE = 'write';
  const READ_WRITE_SCOPE = 'read_write';
  const API_TOKEN = 'api_token';

  /**
   * API
   */
  const API_URL = 'https://api.voucherly.it/v1/';
  const API_SAND_ENV = 0;
  const API_LIVE_ENV = 1;

}