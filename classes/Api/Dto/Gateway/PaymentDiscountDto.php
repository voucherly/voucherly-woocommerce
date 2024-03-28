<?php

namespace Voucherly\Api\Dto\Gateway;

class PaymentDiscountDto {
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