<?php

namespace VoucherlyApi\PaymentGateway;

defined( 'ABSPATH' ) || exit;

class PaymentGateway {

  public function __construct()
  {
  }
  
  public static function list() {
    return \VoucherlyApi\Request::get('payment_gateways');
  }
} 