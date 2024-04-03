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
  const PLUGIN_FOLDER_NAME = "voucherly";
  const DOMAIN = self::PLUGIN_FOLDER_NAME;
  const WEB_PLUGIN_PATH = '/wp-content/plugins/'.self::PLUGIN_FOLDER_NAME.'/';
  const PLUGIN_FOLDER_PATH = ABSPATH.'wp-content/plugins/'.self::PLUGIN_FOLDER_NAME.'/';
  const CSS_ASSETS = self::WEB_PLUGIN_PATH.'includes/ui/assets/css/';
  const JS_ASSETS = self::WEB_PLUGIN_PATH.'includes/ui/assets/js/';


  /**
   * Admin constants
   */


  const MAP_CARRIERS = 'map_carriers';
  const LIVE_API = 'live_api';
  const CATEGORY_IS_FOOD = 'category_is_food';
  const API_KEY = 'api_key';
  const API_KEY_SAND = 'api_key_sand';
}