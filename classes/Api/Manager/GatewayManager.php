<?php

namespace Voucherly\Api\Manager;

use Voucherly\Api\ApiCaller;
use Voucherly\Api\Dto\Gateway\PaymentDto;

class GatewayManager extends ApiCaller {

  public function __construct($accessTokenProvider)
  {
    parent::__construct($accessTokenProvider);
  }

  public function createPaymentWithNewCustomer(PaymentDto $paymentDto){
    return $this->makeRequest('payments',self::REQUEST_POST,$paymentDto);
  }
  
  public function createPaymentWithS2s(PaymentDto $paymentDto){
    return $this->makeRequest('payments',self::REQUEST_POST,$paymentDto);
  }

  public function confirm(string $payment_id){
    return $this->makeRequest('payments/'.$payment_id.'/confirm',self::REQUEST_POST);
  }

  public function refund(string $payment_id){
    return $this->makeRequest('payments/'.$payment_id.'/refund',self::REQUEST_POST);
  }
} 