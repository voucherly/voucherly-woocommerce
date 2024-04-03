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
    if ((!empty($_GET['section'])) && ($_GET['section'] == 'voucherly')) {
      $GLOBALS['hide_save_button'] = false;
    }

    $this->id                 = "voucherly";
    $this->method_title       = "Voucherly";
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

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'gateway_api' ) );

    \VoucherlyApi\Api::setApiKey($this->get_option('apiKey-live'), "live");
    \VoucherlyApi\Api::setApiKey($this->get_option('apiKey-sand'), "sand");
    \VoucherlyApi\Api::setSandbox($this->get_option('sandbox'));
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woo-voucherly'),
        'type' => 'checkbox',
        'label' => __('Enable Voucherly', 'woo-voucherly'),
        'default' => 'yes',
      ),
      'apiKey-live' => array(
        'title' => __('API key live', 'woo-voucherly'),
        'type' => 'text',
        'description' => sprintf(__('Locate API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'woo-voucherly'), 'https://dashboard.voucherly.it')
      ),
      'apiKey-sand' => array(
        'title' => __('API key sand', 'woo-voucherly'),
        'type' => 'text',
        'description' => sprintf(__('Locate API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'woo-voucherly'), 'https://dashboard.voucherly.it')
      ),
      'sandbox' => array(
        'title' => __('Sandbox', 'woo-voucherly'),
        'label' => __('Sandbox Mode', 'woo-voucherly'),
        'type' => 'checkbox',
        'default' => 'no',
        'description' => __('Sandbox Mode can be used to test payments.', 'woo-satispay')
      ),
      'finalizeUnhandledTransactions' => array(
        'title' => __('Finalize unhandled payments', 'woo-voucherly'),
        'label' => __('Enable cron', 'woo-voucherly'),
        'type' => 'checkbox',
        'default' => 'no',
        'description' => sprintf(__('Finalize unhandled Voucherly payments with a cron.', 'woo-voucherly'))
      ),
      'finalizeMaxHours' => array(
        'title' => __('Finalize pending payments up to', 'woo-voucherly'),
        'label' => __('Finalize pending payments up to', 'woo-voucherly'),
        'type' => 'integer',
        'default' => 4,
        'description' => sprintf(__('Choose a number of hours, default is four and minimum is two.', 'woo-voucherly'))
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
      $order->update_meta_data( 'voucherly_environment', \VoucherlyApi\Api::getEnvironment() );

      WC()->session->set(self::SessionPaymentIdKey, $payment->id);
  
      update_user_meta(get_current_user_id(), $this->getVoucherlyCustomerUserMetaKey(), $payment->customerId);

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

  public function finalize_orders() {
    if ($this->get_option('finalizeUnhandledTransactions') === 'yes' && $this->get_option('enabled') === 'yes') {
      $rangeStart = $this->get_start_date_scheduled_time();
      $rangeEnd = $this->get_end_date_scheduled_time();
      $orders = wc_get_orders(array(
        'limit' => -1,
        'type' => 'shop_order',
        'status' => array('pending','on-hold'),
        'date_created'=> $rangeStart .'...'. $rangeEnd
      )
    );
    foreach ($orders as $order) {
      try {
        if ($order->get_payment_method() === 'voucherly') {
          $transactionId = $order->get_transaction_id();
          if (!isset($transactionId)) {
            continue;
          }
          //callback logic
          $payment = VoucherlyApi\Payment\Payment::get($transactionId);
          if ($order->has_status(wc_get_is_paid_statuses())) {
            continue;
          }
          if (self::paymentIsPaidOrCaptured($payment)) {
            $order->payment_complete($payment->id);
            $order->add_order_note('The Voucherly Payment has been finalized by custom cron action');
            $order->save();

            continue;
          }
          if ($payment->status === 'CANCELED') {
              $order->update_status("cancelled");
              $order->add_order_note('The Voucherly Payment has been cancelled by custom cron action');
              $order->save();
          }
        }
      } catch (\Exception $e) {
          if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->debug('An error occured when finalizing the order ' . $order->get_order_number() .
            '. Error: ' . $e->getMessage(),
            array('source' => 'voucherly'));
          }
        }
      }
    }
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

        if (self::paymentIsPaidOrCaptured($payment)) {
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
        
        if (!self::paymentIsPaidOrCaptured($payment)) {
          exit;
        }

        $order->payment_complete($payment->id);
        
        header('Content-Type: application/json');
        exit(
          json_encode(array(
            'isSuccess' => true,
            'orderId' => $orderId
          ))
        );
    }
  }

  public function process_admin_options() {


    $liveOk = $this->processApiKey("live");
    if (!$liveOk){
      return false;
    }

    $sandOk = $this->processApiKey("sand");
    if (!$sandOk){
      return false;
    }

    $postData = $this->get_post_data();
    $newSandbox = $postData['woocommerce_voucherly_sandbox'];
    \VoucherlyApi\Api::setSandbox($newSandbox);

    return parent::process_admin_options();
  }

  private function processApiKey($environment) : bool {

    $postData = $this->get_post_data();

    $optionKey = 'apiKey-'.$environment;

    $apiKey = $this->get_option($optionKey);
    $newApiKey = $postData['woocommerce_voucherly_'.$optionKey];

    if (empty($newApiKey) || $newApiKey == $apiKey) {
      return true;
    }

    try {


      $ok = \VoucherlyApi\Api::testAuthentication($newApiKey);
      if (!$ok) {
        echo '<div class="notice-error notice">';
        echo '<p>'.sprintf(__('The "%s" is invalid', 'woo-voucherly'), __('API key '.$environment, 'woo-voucherly')).'</p>';
        echo '</div>';
  
        return false;
      }

      $this->update_option($optionKey, $newApiKey);

      \VoucherlyApi\Api::setApiKey($newApiKey, $environment);

      // Delete user metadata (?)

      return true;

    } catch(\Exception $ex) {
      echo '<div class="notice-error notice">';
      echo '<p>'.sprintf(__('The "%s" is exception', 'woo-voucherly'), __('API key '.$environment, 'woo-voucherly')).'</p>';
      echo '</div>';

      return false;
    }
  }

  public function get_transaction_url( $order ) {
    $this->view_transaction_url = "https://dashboard.voucherly.it/pay/payment/details?id=%s";
    return parent::get_transaction_url( $order );
  }


  /**
   * 
   */
  private static function paymentIsVoided($payment) : bool {
    return $payment->status === 'Voided';
  }

  private static function paymentIsPaidOrCaptured($payment) : bool {
    return $payment->status === 'Confirmed' || $payment->status === 'Captured' || $payment->status === 'Paid';
  }

  private static function getVoucherlyCustomerUserMetaKey() : string{
    return "voucherly_customer_".\VoucherlyApi\Api::getEnvironment();;
  }

  

  /**
   * Helper methods to create payment request
   */
  
   private function getPaymentRequest(WC_Order $order) {

    $order_id = $order->get_id();

    $request = new \VoucherlyApi\Payment\CreatePaymentRequest;
    $request->metadata = array(
      "orderId" => strval($order_id)
    );

    $customerId = get_user_meta(get_current_user_id(), $this->getVoucherlyCustomerUserMetaKey(), true);
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
      $line->isFood = true;

      // foreach ($product->get_category_ids() as $category_id) {
 
      //   if ($categoryHelper->isFood($category_id)) {
      //     $line->isFood = true;
      //     break;
      //   }
      // }

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
}

