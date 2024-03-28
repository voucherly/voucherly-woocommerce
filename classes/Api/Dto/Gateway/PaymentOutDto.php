<?php

namespace Voucherly\Api\Dto\Gateway;

class PaymentOutDto {
  
  /**
   * @var string
   */
  public $id = '';
  /**
   * @var string
   */
  public $customerId = '';
  /**
   * @var string
   */
  public $checkoutUrl = '';
}