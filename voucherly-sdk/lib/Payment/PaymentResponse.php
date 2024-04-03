<?php

namespace VoucherlyApi\Payment;

class PaymentResponse {
  
  public string $id;
  public ?string $referenceId;
  public ?string $customerId;
  public string $customerEmail;
  public string $customerFirstName;
  public string $customerLastName;
  public string $status;
  public string $checkoutUrl;
  public $metadata = [];
}