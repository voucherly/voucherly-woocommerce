<?php

namespace Voucherly\Api;

use Voucherly\Plugin\Constants;

/**
 * Caller for APIs
 */
class ApiCaller
{
  private static $CURL_OPTIONS = [
    CURLOPT_URL => '',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'X-API-Key: '
    ),
    CURLOPT_POSTFIELDS => ''
  ];
  const REQUEST_GET = 'GET';
  const REQUEST_POST = 'POST';

  /**
   * @var AccessTokenProvider
   */
  private $accessTokenProvider;

  public function __construct(AccessTokenProvider $accessTokenProvider)
  {
    $this->accessTokenProvider = $accessTokenProvider;
  }

  /**
   * Starts the request and returns the response
   */
  public function makeRequest($route='',$type=self::REQUEST_GET,$params = []){
    $curl = curl_init();

    self::$CURL_OPTIONS[CURLOPT_URL] = (Constants::API_URL).$route;
    self::$CURL_OPTIONS[CURLOPT_CUSTOMREQUEST] = $type;
    self::$CURL_OPTIONS[CURLOPT_HTTPHEADER][1] = 'Voucherly-API-Key: '.$this->accessTokenProvider->access_token;

    if(self::REQUEST_POST==$type){
      if((is_array($params) && count($params)) || !is_array($params)) self::$CURL_OPTIONS[CURLOPT_POSTFIELDS] = json_encode($params);
    }

    curl_setopt_array($curl,self::$CURL_OPTIONS);

    $response = curl_exec($curl);
    $curl_info = curl_getinfo($curl);

    curl_close($curl);

    $code = (int) $curl_info['http_code'];
    if (200 !== $code && 201 !== $code) {
      $this->accessTokenProvider->logger->error('Request error');
        
      $this->accessTokenProvider->logger->error('error: {statuscode}', ['statuscode' => $curl_info['http_code']]);
        
      $this->accessTokenProvider->logger->error('error: {error}', ['error' => $response]);

      return false;
    }

    return json_decode($response);
  }
}
