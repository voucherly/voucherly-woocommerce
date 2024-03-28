<?php

namespace Voucherly\Api;

use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;

/**
 * Access Token Provider
 */
class AccessTokenProvider
{
  use TraitEnv;

  protected $clientId;
  protected $clientSecret;
  /**
   * @var ApiLogger
   */
  public $logger;
  public $env;
  /**
   * @var array
   */
  public $access_token = [];

  /**
   * @param ApiLogger $logger
   * @param string    $clientId
   * @param string    $clientSecret
   */
  public function __construct($logger, $clientId, $clientSecret, $env)
  {
    $this->logger = $logger;
    $this->clientId = $clientId;
    $this->clientSecret = $clientSecret;
    $this->setupEnv($env);
  }

  /**
   * @return void
   */
  public function bootstrap()
  {
    $this->access_token = AdminSettings::exists(Constants::API_TOKEN) ? 
      AdminSettings::get(Constants::API_TOKEN) : 
      md5($this->clientId.':'.$this->clientSecret);
  }
}
