<?php

namespace Voucherly\Woocommerce\Payment;

use Voucherly\Api\ApiCreator;
use Voucherly\Plugin\Constants;
use Voucherly\Traits\GatewayTrait;
use Voucherly\Plugin\Database\EntityManager;
use Voucherly\Api\Dto\Gateway\PaymentOutDto;
use Voucherly\Enum\RefundType;
use Voucherly\Plugin\AdminSettings;
use Voucherly\Api\Dto\Gateway\PaymentDto;
use WC_Payment_Gateway;

class Gateway extends WC_Payment_Gateway
{

  use GatewayTrait;

  public static function getPaymentMethod($gateways){
    $gateways[] = Gateway::class;
    return $gateways;
  }

  public static function refund($order_id, $old_status, $new_status, $order){
    if(AdminSettings::exists(Constants::MAP_ORDERS) && $new_status==AdminSettings::get(Constants::MAP_ORDERS) && AdminSettings::exists(Constants::REFUND_TYPE) && AdminSettings::get(Constants::REFUND_TYPE)==RefundType::AUTO){
      $order_model = (new EntityManager($order))->getOrder();
      if($order_model){
        ApiCreator::getGatewayInstance()->refund(
          $order_model->transaction_id
        );
      }
    }
  }

  public static function error(){
    if(!isset($_GET['error-voucherly']) || 1!=$_GET['error-voucherly']) return;
    require_once(Constants::PLUGIN_FOLDER_PATH.'/includes/ui/view/payment/error.php');
  }

  public function __construct()
  {
    $this->id = Constants::DOMAIN;
    $this->method_title = Constants::PLUGIN_NAME;
    $this->title = Constants::PLUGIN_NAME;

    $this->has_fields = false;

    $this->init_form_fields();
    $this->init_settings();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action('woocommerce_thankyou_' . $this->id, array($this, 'order_received'));
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Abilita/Disabilita',Constants::DOMAIN),
        'type' => 'checkbox',
        'label' => __('Abilita questo metodo di pagamento',Constants::DOMAIN),
        'default' => 'yes',
      ),
    );
  }

  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);
    $order_data = $order->get_data();
    $wp_user_id = get_current_user_id();
    $entity_manager = (new EntityManager($order));

    $customerId = $entity_manager->getCustomerIdByWordpressUserId($wp_user_id);

    /**
     * @var PaymentDto
     */
    $paymentDto = $this->getPayment($order);
    if(!$customerId){
      /**
       * @var PaymentOutDto
       */
      $response = ApiCreator::getGatewayInstance()->createPaymentWithNewCustomer(
        $paymentDto
      );
    }

    $paymentDto->customerId = !$customerId ? $response->customerId : $customerId;
    $paymentDto->s2SUrl = Router::addRoutes()['success'].(stripos(Router::addRoutes()['success'],'?')===false ? '?' : '&').'orderId='.$order_id;
    $response = ApiCreator::getGatewayInstance()->createPaymentWithS2s(
      $paymentDto
    );

    $entity_manager->saveOrderWithSuccess($response->customerId,$response->id,json_encode($response),$wp_user_id);

    return array(
      'result' => 'success',
      'redirect' => $response->checkoutUrl,
    );
  }

  public function order_received($order_id)
  {
  }
}
