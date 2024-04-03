<?php

namespace VoucherlyApi;

class Api
{
  private static $env = "production";
  private static $apiKey;
  private static $apiKeySandbox;
  private static $securityBearer;
  private static $version = "1.0.2";
  private static $authservicesUrl = "https://authservices.satispay.com";

  public static function getApiKey() {
    return self::$apiKey;
  }
  
  public static function setApiKey($value) {
    self::$apiKey = $value;
  }

  public static function getApiKeySandboxt() {
    return self::$apiKeySandbox;
  }

  public static function setApiKeySandbox($value) {
    self::$apiKeySandbox = $value;
  }
  
  public static function getEnvironment() {
    return self::$env;
  }
  
  public static function setEnvironment($value) {
    self::$env = $value;
  }
  
}
