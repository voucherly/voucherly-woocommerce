<?php

namespace Voucherly\Plugin\Database;

use Voucherly\Api\ApiBootstrap;
use Voucherly\Plugin\Constants;
use WC_Order;

/**
 * Voucherly woocommerce wrapper
 */
class EntityManager{

  /**
   * @var Wc_Order
   */
  private $order;
  /**
   * @var string
   */
  private $table_name = '';
  /**
   * @var \wpdb
   */
  private $wpdb;

  public function __construct($order = null)
  {
    global $wpdb;

    $this->wpdb = $wpdb;
    if($order) $this->order = $order;
    $this->table_name = $this->wpdb->prefix.Constants::SUFFIX_DATABASE.'orders';
  }

  public function setOrder(WC_Order $order){
    $this->order = $order;
  }

  /**
   * Saves order after sending it to VOUCHERLY API
   */
  public function saveOrderWithSuccess(string $customer_id = '', string $transaction_id = '', string $details = '', int $user_id = 0){

    $this->addOrUpdateOrder('environment',$this->getEnvironment());
    $this->addOrUpdateOrder('customer_id',$customer_id);
    $this->addOrUpdateOrder('details',$details);
    $this->addOrUpdateOrder('transaction_id',$transaction_id);
    $this->addOrUpdateOrder('id_user',$user_id);
  }

  /**
   * Saves order after sending it to VOUCHERLY API
   */
  public function saveOrderWithError(string $error = ''){

    $this->addOrUpdateOrder('error',$error);
  }

  /**
   * Retrieves the voucherly order from order WC
   */
  public function getOrder(){
    if(!$this->order) return false;
    
    $result_order = $this->wpdb->get_results('SELECT * FROM '.$this->table_name.' WHERE id_order='.$this->order->get_id().' AND environment='.$this->getEnvironment());

    return empty($result_order) ? false : $result_order[0];
  }

  /**
   * Retrieves the voucherly order from customer_id
   */
  public function getOrderByCustomerId($transaction_id, $customer_id){
    $result_order = $this->wpdb->get_results('SELECT * FROM '.$this->table_name.' WHERE customer_id="'.$customer_id.'" AND transaction_id="'.$transaction_id.'" AND environment='.$this->getEnvironment());

    return empty($result_order) ? false : $result_order[0];
  }

  /**
   * Retrieves the voucherly order from customer_id
   */
  public function getCustomerIdByWordpressUserId($user_id){
    $result_order = $this->wpdb->get_results('SELECT * FROM '.$this->table_name.' WHERE id_user="'.$user_id.'" AND environment='.$this->getEnvironment());

    return empty($result_order) ? false : $result_order[0]->customer_id;
  }

  /**
   * Retrieves the voucherly order from order_id
   */
  public function getOrderById($order_id){
    $result_order = $this->wpdb->get_results('SELECT * FROM '.$this->table_name.' WHERE id_order="'.$order_id.'" AND environment='.$this->getEnvironment());

    return empty($result_order) ? false : $result_order[0];
  }

  private function addOrUpdateOrder($type='uuid',$value=''){
    $order = $this->getOrder();
    if($order===false){
      $this->wpdb->query('INSERT INTO '.$this->table_name.' (id_order,'.$type.') VALUES ('.$this->order->get_id().',\''.$value.'\')');
    }else{
      $this->wpdb->query('UPDATE '.$this->table_name.' SET '.$type.'=\''.$value.'\', date_upd=NOW() WHERE id_order='.$this->order->get_id());
    }
  }

  private function getEnvironment(): int{
    return ApiBootstrap::bootStrap()->getEnvironment();
  }
}