<?php

namespace Voucherly\Api\Dto\Gateway;

class PaymentProductDto {
  /**
   * @var int
   */
  public $quantity = 0;
  /**
   * @var int  
   */
  public $unitAmount = 0;
  /**
   * @var int
   */
  public $unitDiscountAmount = 0;
  /**
   * @var int
   */
  public $discountAmount = 0;
  /**
   * @var string
   */
  public $productName = '';
  /**
   * @var string
   */
  public $productImage = '';
  /**
   * @var bool
   */
  public $isFood = false;
}