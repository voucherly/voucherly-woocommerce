<?php

namespace Voucherly\Woocommerce\Payment;

use Voucherly\Api\ApiCreator;
use Voucherly\Plugin\Database\EntityManager;

class Validator
{

  /**
   * @var int
   */
  private $order_id;

  public function __construct($order_id)
  {
    $this->order_id = $order_id;
  }

  public function validate()
  {
    /**
     * Validate order and mark as completed
     */
    $order = (new EntityManager())->getOrderById($this->order_id);

    if ($order === false) {
      return false;
    }

    return $this->order_id;
  }
}
