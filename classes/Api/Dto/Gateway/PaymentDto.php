<?php

namespace Voucherly\Api\Dto\Gateway;

class PaymentDto {
  
  /**
   * @var string
   */
  public $customerFirstName = '';
  /**
   * @var string
   */
  public $customerLastName = '';
  /**
   * @var string
   */
  public $customerEmail = '';
  /**
   * @var string
   */
  public $mode = 'Payment';
  /**
   * @var string
   */
  public $redirectSuccessUrl = '';
  /**
   * @var string
   */
  public $redirectErrorUrl = '';
  /**
   * @var string
   */
  public $s2SUrl = '';
  /**
   * @var string
   */
  public $language = '';
  /**
   * @var string
   */
  public $country = '';
  /**
   * @var string
   */
  public $shippingAddress = '';
  /**
   * @var PaymentProductDto[]
   */
  public $lines = [];
  /**
   * @var PaymentDiscountDto[]
   */
  public $discounts = [];
}