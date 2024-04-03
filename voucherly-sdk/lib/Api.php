<?php

namespace VoucherlyApi;

class Api
{
  private static $env = "production";
  private static $apiKeyLive;
  private static $apiKeySandbox;


  public static function testAuthentication($apiKey = null): bool {
    try {

      $test = $apiKey == null
        ? Request::get("payments/woocommerce")
        : Request::get_on_demand($apiKey, "payments/woocommerce");

      return true;

    } catch(NotSuccessException $ex) {

      if ($ex->getCode() == 401) {  
        return false;
      }

      return true;
    }
  }

  public static function getApiKey() {
    return self::$env == "live" ? self::$apiKeyLive : self::$apiKeySandbox;
  }
  
  public static function setApiKey($value, $environment) {
    if ($environment == "live") {
      self::$apiKeyLive = $value;
    }
    else {
      self::$apiKeySandbox = $value;
    }
  }
  
  public static function getEnvironment() {
    return self::$env;
  }
  
  public static function setSandbox($sandbox) {
    self::$env = $sandbox == 'no' ? "live" : "sand";
  }
  
}
