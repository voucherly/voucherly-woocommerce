<?php

namespace VoucherlyApi\Payment;

defined( 'ABSPATH' ) || exit;

class CreatePaymentRequestDiscount {
  /**
   * @var string
   */
  public $discountName = '';
  /**
   * @var string
   */
  public $discountDescription = '';
  /**
   * @var int
   */
  public $amount = 0;

}