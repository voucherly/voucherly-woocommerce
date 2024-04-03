<?php

use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;
use Voucherly\Woocommerce\Category;

require_once(__DIR__ . '/voucherly-sdk/init.php');

class WC_Payment_Voucherly extends WC_Payment_Gateway
{
  const SessionPaymentIdKey = 'voucherly_payment_id';

  public function __construct()
  {
    $this->id                 = Constants::DOMAIN;
    $this->method_title       = Constants::PLUGIN_NAME;
    $this->method_description = "Accetta pagamenti tramite buoni pasto per il tuo ecommerce. Non perdere neanche una vendita, incassa online in totale sicurezza e in qualsiasi modalità.";
    $this->has_fields         = false;
    $this->supports           = array(
      'products',
      'refunds'
    );

    $this->title              = "Voucherly (carte di pagamento, buoni pasto e altri metodi)";
    $this->description        = "Verrai reindirizzato al portale di Voucherly dove potrai pagare con i tuoi buoni pasto o con carta di credito.";

    $this->init_form_fields();
    $this->init_settings();

    // add_action('woocommerce_order_status_changed', 'refund', 10, 4);

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'gateway_api' ) );

    \VoucherlyApi\Api::setApiKey(AdminSettings::get(Constants::API_KEY));
    \VoucherlyApi\Api::setApiKeySandbox(AdminSettings::get(Constants::API_KEY_SAND));
    \VoucherlyApi\Api::setEnvironment(AdminSettings::exists(Constants::LIVE_API) ? Constants::API_LIVE_ENV : Constants::API_SAND_ENV);
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Abilita/Disabilita', Constants::DOMAIN),
        'type' => 'checkbox',
        'label' => __('Abilita Voucherly', Constants::DOMAIN),
        'default' => 'yes',
      // ),
      // 'title' => array(
      //   'title' => __( 'Title', 'woocommerce' ),
      //   'type' => 'text',
      //   'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
      //   'default' => __( 'Cheque Payment', 'woocommerce' ),
      //   'desc_tip' => true,
      // ),
      // 'description' => array(
      //   'title' => __( 'Customer Message', 'woocommerce' ),
      //   'type' => 'textarea',
      //   'default' => ''
      )
    );
  }

  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);

    $request = $this->getPaymentRequest($order);

    $payment = \VoucherlyApi\Payment\Payment::create($request);

    try {
      $order->set_transaction_id($payment->id);
      WC()->session->set(self::SessionPaymentIdKey, $payment->id);
  
      update_user_meta(get_current_user_id(), $this->GetVoucherlyCustomerUserMetaKey(), $payment->customerId);

      $order->save();
    } catch (\Exception $e) {
      if (function_exists('wc_get_logger')) {
          $logger = wc_get_logger();
          $logger->debug(
              'Order id - ' . $order->get_id() . ' - Could not save transaction Id for payment due to the following error: ' . $e->getMessage(),
              array('source' => 'voucherly')
          );
      }
    }

    return array(
      'result' => 'success',
      'redirect' => $payment->checkoutUrl
    );
  }

  
  public function process_refund($order, $amount = null, $reason = '') {
    $order = new WC_Order($order);

    if ($amount != null && $amount != $order->get_total()){
      return new WP_Error('partial', "Se vuoi gestire un rimborso parziale utilizza la dashboard di Voucherly.");
    }

    try {
      $response = VoucherlyApi\Payment\Payment::refund($order->get_transaction_id());

      return isset($response->status) && ($response->status === 'Refunded' || $response->status === 'Cancelled');
    } catch (\Exception $e) {
      error_log('Voucherly Refund Error: ' . $e->getMessage());
    }

    return false;
  }

  public function gateway_api() {
    switch($_GET['action']) {
      case 'redirect':
        $paymentId = WC()->session->get(self::SessionPaymentIdKey);
        if (!$paymentId) {
            header('Location: '.$this->get_return_url(''));
            break;
        }

        $payment = \VoucherlyApi\Payment\Payment::get($paymentId);
        $order = new WC_Order($payment->metadata->orderId);

        if ($payment->status === 'Confirmed' || $payment->status === 'Captured' || $payment->status === 'Paid') {
          header('Location: '.$this->get_return_url($order));
        } else if ($payment->status === 'Voided') {
          header('Location: '. wc_get_checkout_url());
        } else {
          header('Location: '. wc_get_cart_url());
        }

        break;
      case 's2s':
        $orderId = $_GET['orderId'];
        if (!$orderId) {
            header('Location: '.$this->get_return_url(''));
            break;
        }

        $order = new WC_Order($orderId);

        if ($order->has_status(wc_get_is_paid_statuses())) {
          exit;
        }

        $payment = \VoucherlyApi\Payment\Payment::get($order->get_transaction_id());

        header('Content-Type: application/json');
        if ($payment->status === 'Confirmed' || $payment->status === 'Captured' || $payment->status === 'Paid') {
          $order->payment_complete($payment->id);
          
          exit(
            json_encode(
              [
                'isSuccess' => true,
                'orderId' => $orderId
              ]
            )
          );
        }
        else {
          $order->update_status("cancelled");

          
          exit(
            json_encode(
              [
                'isSuccess' => false
              ]
            )
          );
        }

        break;
    }
  }

  public function get_transaction_url( $order ) {
    $this->view_transaction_url = "https://dashboard.voucherly.it/pay/payment/details?id=%s";
    return parent::get_transaction_url( $order );
  }

  
  public function getPaymentRequest(WC_Order $order) {

    $order_id = $order->get_id();

    $request = new \VoucherlyApi\Payment\CreatePaymentRequest;
    $request->metadata = array(
      "orderId" => strval($order_id)
    );

    $customerId = get_user_meta(get_current_user_id(), $this->GetVoucherlyCustomerUserMetaKey(), true);
    if ($customerId != ''){
      $request->customerId = $customerId;
    }
    $request->customerFirstName = $order->get_billing_first_name();
    $request->customerLastName = $order->get_billing_last_name();
    $request->customerEmail = $order->get_billing_email();

    $apiUrl = WC()->api_request_url('WC_Gateway_Voucherly');
    $request->s2SUrl = add_query_arg( array(
      'action' => 's2s',
      'orderId' => $order_id,
    ), $apiUrl );
    
    // orderId passed by session
    $redirectUrl = add_query_arg( array(
      'action' => 'redirect',
    ), $apiUrl );
    $request->redirectSuccessUrl = $redirectUrl;
    $request->redirectErrorUrl = $redirectUrl;

    $request->shippingAddress = $order->get_formatted_billing_address();
    $request->country = $order->get_billing_country();
    $request->language = explode('_', get_locale())[0];

    $request->lines = $this->getPaymentLines($order);
    $request->discounts = $this->getPaymentDiscounts();

    return $request;
  }

  private function getPaymentLines(WC_Order $order) {
    $lines = [];

    $cart_items = WC()->cart->get_cart();
    /**
     * @var Category
     */
    $categoryHelper = Category::getInstance();

    foreach($cart_items as $key => $item){
      
      $product = wc_get_product($item['product_id']);

      $line = new \VoucherlyApi\Payment\CreatePaymentRequestLine();
      $line->productName = $product->get_name();
      $line->productImage = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()),'full')[0];
      $line->unitAmount = round($product->get_regular_price() * 100);
      if ($product->get_sale_price()) {
        $line->unitDiscountAmount = round($line->unitAmount - $product->get_sale_price() * 100);
      }
      $line->quantity = $item['quantity'];
      foreach ($product->get_category_ids() as $category_id) {
 
        if ($categoryHelper->isFood($category_id)) {
          $line->isFood = true;
          break;
        }
      }

      $lines[] = $line;
    }

    
    foreach ($order->get_shipping_methods() as $shipping_method) {
      if ($shipping_method->get_total() <= 0) continue;
      
      $shipping = new \VoucherlyApi\Payment\CreatePaymentRequestLine();
      $shipping->productName = $shipping_method->get_method_title();
      $shipping->unitAmount = round($shipping_method->get_total() * 100);
      $shipping->quantity = 1;

      $lines[] = $shipping;
    }

    return $lines;
  }

  private function getPaymentDiscounts() {
    $discounts = [];

    $coupons = WC()->cart->get_applied_coupons();

    foreach($coupons as $coupon_code) {
      $coupon = new WC_Coupon($coupon_code);

      $discount = new VoucherlyApi\Payment\CreatePaymentRequestDiscount();
      $discount->discountName = $coupon->get_code();
      $discount->amount = round($coupon->get_amount() * 100);

      $discounts[] = $discount;
    }

    return $discounts;
  }

  private static function GetVoucherlyCustomerUserMetaKey() : string{
    return "voucherly_customer_".\VoucherlyApi\Api::getEnvironment();;
}
}
