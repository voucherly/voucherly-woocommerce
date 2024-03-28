<?php

namespace Voucherly\Plugin;

use Voucherly\Woocommerce\WebserviceManager;

/**
 * Class for saving and loading settings
 */
class AdminSettings{

  /**
   * @var \wpdb
   */
  private $wpdb;

  public function __construct(){
    global $wpdb;

    $this->wpdb = $wpdb;
  }


  /**
   * Returns existing domain option
   */
  public static function exists($key): bool{
    $option = self::get($key);

    return $option && !empty($option) && !is_null($option);
  }

  /**
   * Returns domain option
   */
  public static function get($key){
    $option = get_option(Constants::DOMAIN.'_'.$key,false);

    return $option;
  }

  /**
   * Removes domain option
   */
  public static function remove($key){
    delete_option(Constants::DOMAIN.'_'.$key);
  }

  /**
   * Updates domain option
   */
  public static function update($key,$value){
    update_option(Constants::DOMAIN.'_'.$key,$value);
  }

  public function saveSettings(): bool{
    if(isset($_POST) && isset($_POST['save'])){
      /**
       * Remove log option for security reasons before saving
       */
      self::remove(Constants::LOG);
      self::remove(Constants::LIVE_API);
      
      foreach($_POST as $key => $value){
        if($key==Constants::MAP_CARRIERS){
          /**
           * Saves carrier mapping
           */
          (new CarrierManager)->saveCarriersMapList($value);
          continue;
        }
        self::update($key,$value);
      }

      /**
       * Generates the WS WC key only if client and secret are available
       */
      //(new WebserviceManager)->generateWebServiceKey();
    }

    return true;
  }

  /**
   * Loads all the settings of the plugin domain
   */
  public function loadSettings(): array{
    $settings = [];

    foreach(
      $this->wpdb->get_results('SELECT * FROM '.$this->wpdb->prefix.'options WHERE option_name LIKE "'.Constants::DOMAIN.'%"')
      as $item
    ){
      $settings[$item->option_name] = $item->option_value;
    }
    return $settings;
  }

}