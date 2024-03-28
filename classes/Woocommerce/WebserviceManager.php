<?php

namespace Voucherly\Woocommerce;

use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;
use Voucherly\Plugin\PluginValidator;
use WC_Auth;

/**
 * Generates web service key for WooCommerce
 */
class WebserviceManager extends WC_Auth{

  /**
   * @var \wpdb
   */
  private $wpdb;

  public function __construct(){
    global $wpdb;

    $this->wpdb = $wpdb;
  }
  /**
   * Tells if the web service key is already installed or not
   */
  public function existsWebServiceKey(): bool{
    return AdminSettings::exists(Constants::WS_KEY)!==false && AdminSettings::exists(Constants::WS_KEY_SECRET)!==false;
  }

  /**
   * Generates WC web service key
   */
  public function generateWebServiceKey($scope=Constants::READ_SCOPE){
    if($this->existsWebServiceKey() || !PluginValidator::getInstance()->isActive()) return false;
    
    $users = $this->wpdb->get_results('SELECT * FROM '.$this->wpdb->prefix.'users');

    foreach($users as $user){
      $user_data = get_userdata($user->ID);

      if($user_data->user_level>=8){ //Level 8 or greater are admin levels
        $ws_key = $this->create_keys(Constants::PLUGIN_NAME,$user->ID,$scope);
        AdminSettings::update(Constants::WS_KEY,$ws_key['consumer_key']);
        AdminSettings::update(Constants::WS_KEY_SECRET,$ws_key['consumer_secret']);
        break;
      }
    }

  }
}