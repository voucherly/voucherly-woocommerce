<?php

namespace Voucherly\Plugin;

use Voucherly\Plugin\AdminSettings;

/**
 * Class to handle info and settings related to the plugin
 */
class PluginValidator{

  /**
   * @var PluginValidator
   */
  public static $instance = null;

  /**
   * Singleton pattern
   */
  public static function getInstance(): PluginValidator{
    if(!is_null(self::$instance)) return self::$instance;
    
    self::$instance = new PluginValidator();;

    return self::$instance;
  }

  /**
   * Tells if the plugin is active or not by checking google api key existance
   */
  public function isActive(): bool{
    return false!==$this->isClientAndSecretAvailable();
  }

  /**
   * Returns if the client and secret are available
   */
  public function isClientAndSecretAvailable(): bool{
    return AdminSettings::exists(Constants::API_KEY) !== false || AdminSettings::exists(Constants::API_KEY_SAND) !== false;
  }

}