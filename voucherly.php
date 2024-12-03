<?php

defined( 'ABSPATH' ) || exit;

require_once (__DIR__ . '/voucherly-sdk/init.php');

class Voucherly extends WC_Payment_Gateway
{
  const SessionPaymentIdKey = 'voucherly_payment_id';
  
  const TITLE = 'Voucherly (carte di pagamento, buoni pasto e altri metodi)';
  const DESCRIPTION = 'Verrai reindirizzato al portale di Voucherly dove potrai pagare con i tuoi buoni pasto o con carta di credito.';
  const SUPPORTS = array(
      'products',
      'refunds'
  );

  public function __construct()
  {
    
    $this->id                 = "voucherly";
    $this->method_title       = "Voucherly";
    $this->method_description = "Accetta buoni pasto con il tuo ecommerce. Non perdere neanche una vendita, incassa online in totale sicurezza e in qualsiasi modalità.";
    $this->has_fields         = false;
    $this->supports           = self::SUPPORTS;

    $this->title              = self::TITLE;
    $this->description        = self::DESCRIPTION;
    $this->icon               = plugins_url('/logo.svg', __FILE__);

    $this->init_form_fields();
    $this->init_settings();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'gateway_api'));

    \VoucherlyApi\Api::setApiKey($this->get_option('apiKey_live'), "live");
    \VoucherlyApi\Api::setApiKey($this->get_option('apiKey_sand'), "sand");
    \VoucherlyApi\Api::setSandbox($this->get_option('sandbox') == "yes");
    
    \VoucherlyApi\Api::setPluginNameHeader('WooCommerce');
    // \VoucherlyApi\Api::setPluginVersionHeader($this->version);
    \VoucherlyApi\Api::setPlatformVersionHeader(WC()->version);
    \VoucherlyApi\Api::setTypeHeader('ECOMMERCE-PLUGIN');
    
    add_action('woocommerce_available_payment_gateways', array($this, 'check_gateway'), 15);
    
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
  }

  public function init_form_fields()
  {
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => true
    ));

    $options = [];
    $options[''] = '';
    foreach ($categories as $category) {
      $options[$category->term_id] = $category->name;
    }

    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'voucherly'),
        'type' => 'checkbox',
        'label' => __('Enable Voucherly', 'voucherly'),
        'default' => 'yes',
      ),
      'apiKey_live' => array(
        'title' => 'API key live',
        'type' => 'text',
        /* translators: %s is replaced with Voucherly Dashboard link */
        'description' => sprintf(__('Locate API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.it')
      ),
      'apiKey_sand' => array(
        'title' => 'API key sandbox',
        'type' => 'text',
        /* translators: %s is replaced with Voucherly Dashboard link */
        'description' => sprintf(__('Locate API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.it')
      ),
      'sandbox' => array(
        'title' => __('Sandbox', 'voucherly'),
        'label' => __('Sandbox Mode', 'voucherly'),
        'type' => 'checkbox',
        'default' => 'no',
        'description' => __('Sandbox Mode can be used to test payments.', 'voucherly')
      ),
      'foodCategory' => array(
        'title' => __('Category for food products', 'voucherly'),
        'type' => 'select',
        'default' => '',
        'options' => $options,
        'description' => __('Select the category that determines whether a product qualifies as food (eligible for meal voucher payment). If no category is selected, all products will be considered food.', 'voucherly')
      ),
      'shippingAsFood' => array(
        'title' => __('Shipping as food', 'voucherly'),
        'label' => __('Consider shipping as food', 'voucherly'),
        'type' => 'checkbox',
        'default' => 'no',
        'description' => __('If shipping is considered food, the customer can pay for it with meal vouchers.', 'voucherly')
      ),
      'finalizeUnhandledTransactions' => array(
        'title' => __('Finalize unhandled payments', 'voucherly'),
        'label' => __('Enable cron', 'voucherly'),
        'type' => 'checkbox',
        'default' => 'no',
        'description' => sprintf(__('Finalize unhandled Voucherly payments with a cron.', 'voucherly'))
      ),
      'finalizeMaxHours' => array(
        'title' => __('Finalize pending payments up to', 'voucherly'),
        'label' => __('Finalize pending payments up to', 'voucherly'),
        'type' => 'integer',
        'default' => 4,
        'description' => sprintf(__('Choose a number of hours, default is four and minimum is two.', 'voucherly'))
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
      $order->update_meta_data('voucherly_environment', \VoucherlyApi\Api::getEnvironment());

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

  public function process_refund($order, $amount = null, $reason = '')
  {
    $order = new WC_Order($order);

    if ($amount != null && $amount != $order->get_total()) {
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

  public function gateway_api()
  {
    if ( !isset($_GET['action']) ) {
      exit;
    }

    switch ( $_GET['action'] ) {
      case 'redirect':
        $paymentId = WC()->session->get(self::SessionPaymentIdKey);
        if (!$paymentId) {
          header('Location: ' . $this->get_return_url(''));
          break;
        }

        $payment = \VoucherlyApi\Payment\Payment::get($paymentId);

        if (self::paymentIsPaidOrCaptured($payment)) {
          $order = new WC_Order($payment->metadata->orderId);
          header('Location: ' . $this->get_return_url($order));
        } else if ($payment->status === 'Voided') {
          header('Location: ' . wc_get_checkout_url());
        } else {
          header('Location: ' . wc_get_cart_url());
        }

        break;
      case 'callback':
        $orderId = sanitize_text_field($_GET['orderId']);
        if (!$orderId) {
          header('Location: ' . $this->get_return_url(''));
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
          wp_json_encode(
            array(
              'ok' => true,
              'orderId' => $orderId
            )
          )
        );
    }
  }

  public function admin_options()
  {
    $ok = \VoucherlyApi\Api::testAuthentication();
    if ( !$ok ) {
      echo '<div class="notice-error notice">';
      /* translators: %s is replaced with Voucherly Dashboard link */
      echo '<p>' . esc_html( sprintf(__('Voucherly is not correctly configured, get an API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.com') ) . '</p>';
      echo '</div>';
    }
    
    return parent::admin_options();
  }


  public function process_admin_options()
  {
    $liveOk = $this->processApiKey("live");
    if (!$liveOk) {
      echoInvalidApiKey('API key live');
      return false;
    }

    $sandOk = $this->processApiKey("sand");
    if (!$sandOk) {
      echoInvalidApiKey('API key sandox');
      return false;
    }

    parent::process_admin_options();

    \VoucherlyApi\Api::setSandbox($this->get_option('sandbox') == "yes");

    $this->getAndUpdatePaymentGateways();
  }

  private function processApiKey($environment): bool
  {
    $optionKey = 'apiKey_' . $environment;

    $apiKey = $this->get_option($optionKey);
    $newApiKey = $this->get_post_data()['woocommerce_voucherly_' . $optionKey];
    
    if (!empty($newApiKey)) {
      $ok = VoucherlyApi\Api::testAuthentication($newApiKey);
      if (!$ok) {
          return false;
      }
    }

    $this->update_option($optionKey, $newApiKey);

    \VoucherlyApi\Api::setApiKey($newApiKey, $environment);

    // Should I delete user metadata?

    return true;
  }
  
  private function echoInvalidApiKey($name) {
    
    echo '<div class="notice-error notice">';
    /* translators: %s is replaced with form label (API key) */
    echo '<p>' . esc_html( sprintf(__('The "%s" is invalid', 'voucherly'), $name) ) . '</p>';
    echo '</div>';
  }  
  
  private function getAndUpdatePaymentGateways() 
  {
    $gateways = $this->getPaymentGateways();
    $this->update_option('gateways_'. VoucherlyApi\Api::getEnvironment(), json_encode($gateways));
  }  

  private function getPaymentGateways() 
  {
      $paymentGatewaysResponse = \VoucherlyApi\PaymentGateway\PaymentGateway::list();
      $paymentGateways = $paymentGatewaysResponse->items; 
      $gateways = [];

      foreach ($paymentGateways as $gateway) {

          if ($gateway->isActive && !$gateway->merchantConfiguration->isFallback ) {

              $formattedGateway["id"] = $gateway->id;
              $formattedGateway["src"] = $gateway->icon ?? $gateway->checkoutImage;
              $formattedGateway["alt"] = $gateway->name;

              $gateways[] = $formattedGateway;
          }
      }

      return $gateways;
  }

  public function is_available()
  {
    if ($this->get_option('enabled') === 'no') {
      return false;
    }
    return true;
  }
  
	public function payment_scripts() {

		wp_register_style( 'voucherly_styles', plugins_url( '/assets/css/voucherly-styles.css',  __FILE__ ), [], "1.1.0" );
		wp_enqueue_style( 'voucherly_styles' );

	}


  public function get_transaction_url($order)
  {
    $this->view_transaction_url = "https://dashboard.voucherly.it/pay/payment/details?id=%s";
    return parent::get_transaction_url($order);
  }


  /**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_icon() {
    
    $gateways = json_decode($this->get_option('gateways_'. \VoucherlyApi\Api::getEnvironment()));
    if ( !isset($gateways) )
    {
      return '';
    }

		foreach ( $gateways as $i ) {
			$icon_html .= '<img src="' . esc_attr( $i->src ) . '" alt="' . esc_attr( $i->alt ) . '" class="voucherly_icon" />';
		}

		// $icon_html .= sprintf( '<a href="%1$s" class="about_voucherly" onclick="javascript:window.open(\'%1$s\',\'Voucherly\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__( 'Che cosa è Voucherly?', 'voucherly' ) . '</a>', "https://voucherly.it" );
		
    return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}


  // START finalize_orders

  public function finalize_orders()
  {
    if ($this->get_option('finalizeUnhandledTransactions') === 'yes' && $this->get_option('enabled') === 'yes') {
      $rangeStart = $this->get_start_date_scheduled_time();
      $rangeEnd = $this->get_end_date_scheduled_time();
      $orders = wc_get_orders(
        array(
          'limit' => -1,
          'type' => 'shop_order',
          'status' => array('pending', 'on-hold'),
          'date_created' => $rangeStart . '...' . $rangeEnd
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
            $logger->debug(
              'An error occured when finalizing the order ' . $order->get_order_number() .
              '. Error: ' . $e->getMessage(),
              array('source' => 'voucherly')
            );
          }
        }
      }
    }
  }

  /**
   * Get the start criteria for the scheduled datetime
   */
  private function get_start_date_scheduled_time()
  {
    $maxHours = $this->get_option('finalizeMaxHours');
    $now = new \DateTime('now', new DateTimeZone('UTC'));
    $scheduledTimeFrame = $maxHours;
    if (is_null($scheduledTimeFrame) || $scheduledTimeFrame == 0 || $scheduledTimeFrame < 0) {
      $scheduledTimeFrame = 4; // DEFAULT_MAX_HOURS
    }
    $tosub = new \DateInterval('PT' . $scheduledTimeFrame . 'H');
    return strtotime($now->sub($tosub)->format('Y-m-d H:i:s'));
  }

  /**
   * Get the end criteria for the scheduled datetime
   */
  private function get_end_date_scheduled_time()
  {
    $now = new \DateTime('now', new DateTimeZone('UTC'));
    // remove just 1 hour so normal transactions can still be processed
    $tosub = new \DateInterval('PT' . 1 . 'H');
    return strtotime($now->sub($tosub)->format('Y-m-d H:i:s'));
  }

  // END finalize_orders

  public function update_payment_gateways()
  {
    if ($this->get_option('enabled') === 'yes') {
      try {

        $this->getAndUpdatePaymentGateways();

      } catch (\Exception $e) {
        if (function_exists('wc_get_logger')) {
          $logger = wc_get_logger();
          $logger->debug(
            'An error occured when updating payment gateways. Error: ' . $e->getMessage(),
            array('source' => 'voucherly')
          );
        }
      }
    }
  }

  /**
   * 
   */
  private static function paymentIsVoided($payment): bool
  {
    return $payment->status === 'Voided';
  }

  private static function paymentIsPaidOrCaptured($payment): bool
  {
    return $payment->status === 'Confirmed' || $payment->status === 'Captured' || $payment->status === 'Paid';
  }

  private static function getVoucherlyCustomerUserMetaKey(): string
  {
    return "voucherly_customer_" . \VoucherlyApi\Api::getEnvironment();
  }


  /**
   * Plugin url.
   *
   * @return string
   */
  public static function plugin_abspath()
  {
    return trailingslashit(plugin_dir_path(__FILE__));
  }

  /**
   * Plugin url.
   *
   * @return string
   */
  public static function plugin_url()
  {
    return untrailingslashit(plugins_url('/', __FILE__));
  }

  /**
   * Check if method has been added correctly
   *
   * @param array
   * @return array
   */
  public function check_gateway($gateways)
  {
    if (isset($gateways[$this->id])) {
      return $gateways;
    }
    if ($this->is_available()) {
      $gateways[$this->id] = $this;
    }

    return $gateways;
  }

  /**
   * Helper methods to create payment request
   */

  private function getPaymentRequest(WC_Order $order)
  {

    $order_id = $order->get_id();

    $request = new \VoucherlyApi\Payment\CreatePaymentRequest;
    $request->metadata = array(
      "orderId" => strval($order_id)
    );

    $customerId = get_user_meta(get_current_user_id(), $this->getVoucherlyCustomerUserMetaKey(), true);
    if ($customerId != '') {
      $request->customerId = $customerId;
    }
    $request->customerFirstName = $order->get_billing_first_name();
    $request->customerLastName = $order->get_billing_last_name();
    $request->customerEmail = $order->get_billing_email();

    $apiUrl = WC()->api_request_url('WC_Gateway_Voucherly');

    // orderId passed by session
    $redirectUrl = add_query_arg(
      array(
        'action' => 'redirect',
      ), $apiUrl);
    $request->redirectOkUrl = $redirectUrl;
    $request->redirectKoUrl = $redirectUrl;

    $callbackUrl = add_query_arg(
      array(
        'action' => 'callback',
        'orderId' => $order_id,
      ), $apiUrl);
    $request->callbackUrl = $callbackUrl;

    $request->shippingAddress = $order->get_formatted_billing_address();
    $request->country = $order->get_billing_country();
    $request->language = explode('_', get_locale())[0];

    $request->lines = $this->getPaymentLines($order);
    $request->discounts = $this->getPaymentDiscounts();

    return $request;
  }

  private function getPaymentLines(WC_Order $order)
  {
    $lines = [];

    $cart_items = WC()->cart->get_cart();

    $foodCategoryId = $this->get_option('foodCategory');

    foreach ($cart_items as $key => $item) {

      $product = wc_get_product($item['product_id']);

      $line = new \VoucherlyApi\Payment\CreatePaymentRequestLine();
      $line->productName = $product->get_name();
      $line->productImage = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'full')[0];
      $line->unitAmount = round($product->get_regular_price() * 100);
      if ($product->get_sale_price()) {
        $line->unitDiscountAmount = round($line->unitAmount - $product->get_sale_price() * 100);
      }
      $line->quantity = $item['quantity'];
      $line->isFood = true;

      if (isset($foodCategoryId) && !empty($foodCategoryId)) {
        $categorys = $product->get_category_ids();
        $line->isFood = in_array($foodCategoryId, $categorys);
      }

      $lines[] = $line;
    }


    foreach ($order->get_shipping_methods() as $shipping_method) {

      $totalWithTax = $shipping_method->get_total() + $shipping_method->get_total_tax();

      if ($totalWithTax  <= 0) {
        continue;
      }

      $shipping = new \VoucherlyApi\Payment\CreatePaymentRequestLine();
      $shipping->productName = $shipping_method->get_method_title();
      $shipping->unitAmount = round($totalWithTax * 100);
      $shipping->quantity = $shipping_method->get_quantity();
      $shipping->isFood = $this->get_option('shippingAsFood') === 'yes';

      $lines[] = $shipping;
    }

    return $lines;
  }

  private function getPaymentDiscounts()
  {
    $discounts = [];

    $coupons = WC()->cart->get_applied_coupons();

    foreach ($coupons as $coupon_code) {

      $coupon = new WC_Coupon($coupon_code);
      $discountAmount = WC()->cart->get_coupon_discount_amount( $coupon_code, false );

      $discount = new VoucherlyApi\Payment\CreatePaymentRequestDiscount();
      $discount->discountName = $coupon->get_code();
      $discount->discountDescription = $coupon->get_description();
      $discount->amount = round($discountAmount * 100);

      $discounts[] = $discount;
    }

    return $discounts;
  }
}

