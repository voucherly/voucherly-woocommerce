<?php

use VoucherlyApi\Api;
use VoucherlyApi\Customer\Customer;
use VoucherlyApi\Payment\CreatePaymentRequest;
use VoucherlyApi\Payment\CreatePaymentRequestDiscount;
use VoucherlyApi\Payment\CreatePaymentRequestLine;
use VoucherlyApi\Payment\Payment;
use VoucherlyApi\PaymentGateway\PaymentGateway;
use VoucherlyApi\PaymentHelper;

defined('ABSPATH') || exit;

require_once __DIR__.'/vendor/autoload.php';

class voucherly extends WC_Payment_Gateway
{
    public const TITLE = 'Voucherly (carte di pagamento, buoni pasto e altri metodi)';
    public const DESCRIPTION = 'Verrai reindirizzato al portale di Voucherly dove potrai pagare con i tuoi buoni pasto o con carta di credito.';
    public const SUPPORTS = [
        'products',
        'refunds',
        // 'tokenization' // La tokenizzazione comporta la gestione dei metodi di pagamento a db. https://developer.woocommerce.com/docs/woocommerce-payment-token-api/
    ];

    public function __construct()
    {
        $this->id = 'voucherly';
        $this->method_title = 'Voucherly';
        $this->method_description = 'Accetta buoni pasto con il tuo ecommerce. Non perdere neanche una vendita, incassa online in totale sicurezza e in qualsiasi modalità.';
        $this->has_fields = false;
        $this->supports = self::SUPPORTS;

        $this->title = self::TITLE;
        $this->description = self::DESCRIPTION;
        $this->icon = plugins_url('/logo.svg', __FILE__);

        $this->view_transaction_url = 'https://dashboard.voucherly.it/pay/payment/details?id=%s';

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_gateway_'.$this->id, [$this, 'gateway_api']);

        $this->loadVoucherlyApiKey();

        Api::setOsNameHeader('WooCommerce');
        Api::setOsVersionHeader(WC()->version);
        Api::setAppNameHeader('voucherly-woocommerce');
        Api::setAppVersionHeader(get_plugin_data(__DIR__)['Version']);
        Api::setDeviceTypeHeader('ECOMMERCE-PLUGIN');

        add_action('woocommerce_available_payment_gateways', [$this, 'check_gateway'], 15);

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
    }

    public function init_form_fields()
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => true,
        ]);

        $options = [];
        $options[''] = '';
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'voucherly'),
                'type' => 'checkbox',
                'label' => __('Enable Voucherly', 'voucherly'),
                'default' => 'yes',
            ],
            'apiKey_live' => [
                'title' => 'API key live',
                'type' => 'text',
                // translators: %s is replaced with Voucherly Dashboard link
                'description' => sprintf(__('Locate API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.it'),
            ],
            'apiKey_sand' => [
                'title' => 'API key sandbox',
                'type' => 'text',
                // translators: %s is replaced with Voucherly Dashboard link
                'description' => sprintf(__('Locate API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.it'),
            ],
            'sandbox' => [
                'title' => __('Sandbox', 'voucherly'),
                'label' => __('Sandbox Mode', 'voucherly'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Sandbox Mode can be used to test payments.', 'voucherly'),
            ],
            'foodCategory' => [
                'title' => __('Category for food products', 'voucherly'),
                'type' => 'select',
                'default' => '',
                'options' => $options,
                'description' => __('Select the category that determines whether a product qualifies as food (eligible for meal voucher payment). If no category is selected, all products will be considered food.', 'voucherly'),
            ],
            'shippingAsFood' => [
                'title' => __('Shipping as food', 'voucherly'),
                'label' => __('Consider shipping as food', 'voucherly'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If shipping is considered food, the customer can pay for it with meal vouchers.', 'voucherly'),
            ],
            'finalizeUnhandledTransactions' => [
                'title' => __('Finalize unhandled payments', 'voucherly'),
                'label' => __('Enable cron', 'voucherly'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Finalize unhandled Voucherly payments with a cron.', 'voucherly'),
            ],
            'finalizeMaxHours' => [
                'title' => __('Finalize pending payments up to', 'voucherly'),
                'label' => __('Finalize pending payments up to', 'voucherly'),
                'type' => 'integer',
                'default' => 4,
                'description' => __('Choose a number of hours, default is four and minimum is two.', 'voucherly'),
            ],
        ];
    }

    // Fired by filter woocommerce_get_customer_payment_tokens
    // It overrides WooCommerce payment tokens (in db)
    public function getVoucherlyCustomerPaymentMethodsAsWoocommercePaymentTokens($customerId, $gatewayId)
    {
        if (!empty($gatewayId) && $gatewayId !== $this->id) {
            return [];
        }

        $customerPaymentMethods = $this->getCustomerPaymentMethods($customerId);

        return $this->customerPaymentMethodsToWoocommercePaymentTokens($customerId, $customerPaymentMethods);
    }

    public function payment_fields()
    {
        // if (!$this->supports('tokenization') || !is_user_logged_in()) {
        if (!is_user_logged_in()) {
            parent::payment_fields();

            return;
        }

        // I can't use them because tokenization is disabled
        // $this->tokenization_script(); // Load necessary tokenization scripts
        // $this->saved_payment_methods(); // Display saved payment methods

        $this->echoCustomPaymentFieldsForCustomerPaymentMethods(get_current_user_id());
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $request = $this->getPaymentRequest($order);

        $payment = Payment::create($request);

        try {
            $order->set_transaction_id($payment->id);
            $order->update_meta_data('voucherly_environment', $payment->tenant);

            update_user_meta(get_current_user_id(), $this->getVoucherlyCustomerUserMetaKey(), $payment->customerId);

            $order->save();
        } catch (Exception $e) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->debug(
                    'Order id - '.$order->get_id().' - Could not save transaction Id for payment due to the following error: '.$e->getMessage(),
                    ['source' => 'voucherly']
                );
            }
        }

        return [
            'result' => 'success',
            'redirect' => $payment->checkoutUrl,
        ];
    }

    public function process_refund($order, $amount = null, $reason = '')
    {
        $order = new WC_Order($order);

        if (null !== $amount && $amount !== $order->get_total()) {
            return new WP_Error('partial', 'Se vuoi gestire un rimborso parziale utilizza la dashboard di Voucherly.');
        }

        try {
            $response = Payment::refund($order->get_transaction_id());

            return isset($response->status) && ('Refunded' === $response->status || 'Cancelled' === $response->status);
        } catch (Exception $e) {
            error_log('Voucherly Refund Error: '.$e->getMessage());
        }

        return false;
    }

    public function gateway_api()
    {
        if (!isset($_GET['action'])) {
            exit;
        }

        switch ($_GET['action']) {
            case 'redirect':
                if (!isset($_GET['success']) || !isset($_GET['status'])) {
                    header('Location: '.wc_get_checkout_url());

                    exit;
                }

                $success = sanitize_text_field(wp_unslash($_GET['success']));
                $status = sanitize_text_field(wp_unslash($_GET['status']));

                if ('Voided' === $status) {
                    header('Location: '.wc_get_checkout_url());

                    exit;
                }

                if (isset($_GET['paymentId'])) {
                    $paymentId = sanitize_text_field(wp_unslash($_GET['paymentId']));
                } elseif (isset($_GET['payment_Id'])) {
                    $paymentId = sanitize_text_field(wp_unslash($_GET['payment_Id']));
                } elseif (isset($_GET['p'])) {
                    $paymentId = sanitize_text_field(wp_unslash($_GET['p']));
                } else {
                    header('Location: '.$this->get_return_url(''));

                    exit;
                }

                $payment = Payment::get($paymentId);
                if (!PaymentHelper::isPaidOrCaptured($payment)) {
                    // $this->warning[] = $this->l('An error occurred during the operation. Don\'t worry, the payment has already been reversed. If you need any assistance, please contact customer service.');
                    header('Location: '.wc_get_checkout_url());

                    exit;
                }

                $order = new WC_Order($payment->metadata->orderId);
                header('Location: '.$this->get_return_url($order));

                break;

            case 'callback':
                $rawBody = file_get_contents('php://input');
                $params = json_decode($rawBody, true);
                if (JSON_ERROR_NONE !== json_last_error() || !isset($params['id'])) {
                    exit('Invalid JSON body');
                }

                $paymentId = $params['id'];
                $payment = Payment::get($paymentId);
                if (!PaymentHelper::isPaidOrCaptured($payment)) {
                    header('Content-Type: application/json');

                    exit(
                        wp_json_encode(
                            [
                                'ok' => false,
                                'error' => 'Payment is not paid or captured',
                            ]
                        )
                    );
                }

                if ('Payment' !== $payment->mode) {
                    header('Content-Type: application/json');

                    exit(
                        wp_json_encode(
                            [
                                'ok' => true,
                            ]
                        )
                    );
                }

                $orderId = $payment->metadata->orderId;
                $order = new WC_Order($orderId);

                if ($order->has_status(wc_get_is_paid_statuses())) {
                    header('Content-Type: application/json');
                    if ($order->get_transaction_id() === $paymentId) {
                        exit(
                            wp_json_encode(
                                [
                                    'ok' => true,
                                    'orderId' => $orderId,
                                ]
                            )
                        );
                    }

                    exit(
                        wp_json_encode(
                            [
                                'ok' => false,
                                'stop' => true,
                                'error' => 'WooCommerce order already paid with different payment method',
                            ]
                        )
                    );
                }

                $order->payment_complete($paymentId);

                exit(
                    wp_json_encode(
                        [
                            'ok' => true,
                            'orderId' => $orderId,
                        ]
                    )
                );
        }
    }

    public function admin_options()
    {
        $ok = Api::testAuthentication();
        if (!$ok) {
            echo '<div class="notice-error notice">';
            // translators: %s is replaced with Voucherly Dashboard link
            echo '<p>'.esc_html(sprintf(__('Voucherly is not correctly configured, get an API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.com')).'</p>';
            echo '</div>';
        }

        return parent::admin_options();
    }

    public function process_admin_options()
    {
        $liveOk = $this->processApiKey('live');
        if (!$liveOk) {
            echoInvalidApiKey('API key live');

            return false;
        }

        $sandOk = $this->processApiKey('sand');
        if (!$sandOk) {
            echoInvalidApiKey('API key sandox');

            return false;
        }

        parent::process_admin_options();

        $this->loadVoucherlyApiKey();

        $this->getAndUpdatePaymentGateways();
    }

    public function is_available()
    {
        if ('no' === $this->get_option('enabled')) {
            return false;
        }

        return true;
    }

    public function payment_scripts()
    {
        wp_register_style('voucherly_styles', plugins_url('/assets/css/voucherly-styles.css', __FILE__), [], '1.1.0');
        wp_enqueue_style('voucherly_styles');
    }

    /**
     * Get_icon function.
     *
     * @since 1.0.0
     *
     * @version 4.0.0
     *
     * @return string
     */
    public function get_icon()
    {
        $gateways = json_decode($this->get_option('gateways'));
        if (!isset($gateways)) {
            return '';
        }

        $icon_html = '';
        foreach ($gateways as $i) {
            $icon_html .= $this->getIconHtml($i->src, $i->alt);
        }

        // $icon_html .= sprintf( '<a href="%1$s" class="about_voucherly" onclick="javascript:window.open(\'%1$s\',\'Voucherly\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__( 'Che cosa è Voucherly?', 'voucherly' ) . '</a>', "https://voucherly.it" );

        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    // START finalize_orders

    public function finalize_orders()
    {
        if ('yes' === $this->get_option('finalizeUnhandledTransactions') && 'yes' === $this->get_option('enabled')) {
            $rangeStart = $this->get_start_date_scheduled_time();
            $rangeEnd = $this->get_end_date_scheduled_time();
            $orders = wc_get_orders(
                [
                    'limit' => -1,
                    'type' => 'shop_order',
                    'status' => ['pending', 'on-hold'],
                    'date_created' => $rangeStart.'...'.$rangeEnd,
                ]
            );
            foreach ($orders as $order) {
                try {
                    if ('voucherly' === $order->get_payment_method()) {
                        $transactionId = $order->get_transaction_id();
                        if (!isset($transactionId)) {
                            continue;
                        }

                        $payment = Payment::get($transactionId);
                        if ($order->has_status(wc_get_is_paid_statuses())) {
                            continue;
                        }
                        if (self::paymentIsPaidOrCaptured($payment)) {
                            $order->payment_complete($payment->id);
                            $order->add_order_note('The Voucherly Payment has been finalized by custom cron action');
                            $order->save();

                            continue;
                        }
                        if ('CANCELED' === $payment->status) {
                            $order->update_status('cancelled');
                            $order->add_order_note('The Voucherly Payment has been cancelled by custom cron action');
                            $order->save();
                        }
                    }
                } catch (Exception $e) {
                    if (function_exists('wc_get_logger')) {
                        $logger = wc_get_logger();
                        $logger->debug(
                            'An error occured when finalizing the order '.$order->get_order_number().
              '. Error: '.$e->getMessage(),
                            ['source' => 'voucherly']
                        );
                    }
                }
            }
        }
    }

    // END finalize_orders

    public function update_payment_gateways()
    {
        if ('yes' === $this->get_option('enabled')) {
            try {
                $this->getAndUpdatePaymentGateways();
            } catch (Exception $e) {
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->debug(
                        'An error occured when updating payment gateways. Error: '.$e->getMessage(),
                        ['source' => 'voucherly']
                    );
                }
            }
        }
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
     * Check if method has been added correctly.
     *
     * @param array
     * @param mixed $gateways
     *
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

    private function echoCustomPaymentFieldsForCustomerPaymentMethods($customerId)
    {
        $customerPaymentMethods = $this->getCustomerPaymentMethods($customerId);
        if (empty($customerPaymentMethods)) {
            parent::payment_fields();
            return;
        }

        echo '<div class="wc-saved-payment-methods">';
        foreach ($customerPaymentMethods as $customerPaymentMethod) {
            if (!isset($customerPaymentMethod->creditCard)) {
                continue;
            }

            $card = $customerPaymentMethod->creditCard;

            if ($card->expirationMonth < date('m') && $card->expirationYear <= date('Y')) {
                continue;
            }

            $brandImagePath = '/assets/images/cards/'.$card->brand.'.png';
            if (!file_exists(__DIR__.$brandImagePath)) {
                $brandImagePath = '/assets/images/cards/default.png';
            }

            echo '<div>';
            echo '<input type="radio" id="wc-'.esc_attr($this->id).'-token-'.esc_attr($customerPaymentMethod->id).'" ';
            echo 'name="wc-'.esc_attr($this->id).'-payment-token" value="'.esc_attr($customerPaymentMethod->id).'" />';
            echo '<label for="wc-'.esc_attr($this->id).'-token-'.esc_attr($customerPaymentMethod->id).'">';
            echo $this->getIconHtml(plugins_url($brandImagePath, __FILE__), $card->brand);
            // echo esc_html(ucfirst($card->brand)). ' ' . esc_html($card->pan);
            echo esc_html($card->pan);
            echo '</label>';
            echo '</div>';
        }

        echo '<div>';
        echo '<input type="radio" id="wc-'.esc_attr($this->id).'-new" name="wc-'.esc_attr($this->id).'-payment-token" value="new" />';
        echo '<label for="wc-'.esc_attr($this->id).'-new">';
        echo esc_html($this->description);
        echo '</label>';
        echo '</div>';
        echo '</div>';
    }

    private function getIconHtml(string $src, string $alt): string
    {
        return '<img src="'.esc_attr($src).'" alt="'.esc_attr($alt).'" class="voucherly_icon" />';
    }

    private function loadVoucherlyApiKey()
    {
        if ('yes' === $this->get_option('sandbox')) {
            Api::setApiKey($this->get_option('apiKey_sand'));
        } else {
            Api::setApiKey($this->get_option('apiKey_live'));
        }
    }

    private function processApiKey($environment): bool
    {
        $optionKey = 'apiKey_'.$environment;

        $apiKey = $this->get_option($optionKey);
        $newApiKey = $this->get_post_data()['woocommerce_voucherly_'.$optionKey];

        if (!empty($newApiKey)) {
            $ok = Api::testAuthentication($newApiKey);
            if (!$ok) {
                return false;
            }
        }

        $this->update_option($optionKey, $newApiKey);

        // Should I delete user metadata?

        return true;
    }

    private function echoInvalidApiKey($name)
    {
        echo '<div class="notice-error notice">';
        // translators: %s is replaced with form label (API key)
        echo '<p>'.esc_html(sprintf(__('The "%s" is invalid', 'voucherly'), $name)).'</p>';
        echo '</div>';
    }

    private function getAndUpdatePaymentGateways()
    {
        $gateways = $this->getPaymentGateways();
        $this->update_option('gateways', wp_json_encode($gateways));
    }

    private function getPaymentGateways()
    {
        $paymentGatewaysResponse = PaymentGateway::list();
        $paymentGateways = $paymentGatewaysResponse->items;
        $gateways = [];

        foreach ($paymentGateways as $gateway) {
            if ($gateway->isActive && !$gateway->merchantConfiguration->isFallback) {
                $formattedGateway['id'] = $gateway->id;
                $formattedGateway['src'] = $gateway->icon ?? $gateway->checkoutImage;
                $formattedGateway['alt'] = $gateway->name;

                $gateways[] = $formattedGateway;
            }
        }

        return $gateways;
    }

    private function getCustomerPaymentMethods($customerId)
    {
        $voucherlyCustomerId = get_user_meta($customerId, $this->getVoucherlyCustomerUserMetaKey(), true);
        if (!isset($voucherlyCustomerId) || empty($voucherlyCustomerId)) {
            return [];
        }

        $key = 'voucherly_payment_methods:'.$voucherlyCustomerId;
        $customerPaymentMethods = get_transient($key);
        if (false === $customerPaymentMethods) {
            $customerPaymentMethods = Customer::paymentMethods($voucherlyCustomerId)->items;
            set_transient($key, $customerPaymentMethods, 60);
        }

        return $customerPaymentMethods;
    }

    private function customerPaymentMethodsToWoocommercePaymentTokens($customerId, $customerPaymentMethods)
    {
        $tokens = [];

        $index = 0;

        foreach ($customerPaymentMethods as $customerPaymentMethod) {
            if (isset($customerPaymentMethod->creditCard)) {
                $card = $customerPaymentMethod->creditCard;

                $token = new WC_Payment_Token_CC();
                $token->set_id($index);
                $token->set_token($customerPaymentMethod->id);
                $token->set_user_id($customerId);
                $token->set_gateway_id($this->id);
                $token->set_last4(substr($card->pan, -4));
                $token->set_expiry_year($card->expirationYear);
                $token->set_expiry_month($card->expirationMonth);
                $token->set_card_type($card->brand);

                $tokens[] = $token;
            }

            ++$index;
        }

        return $tokens;
    }

    /**
     * Get the start criteria for the scheduled datetime.
     */
    private function get_start_date_scheduled_time()
    {
        $maxHours = $this->get_option('finalizeMaxHours');
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $scheduledTimeFrame = $maxHours;
        if (null === $scheduledTimeFrame || 0 === $scheduledTimeFrame || $scheduledTimeFrame < 0) {
            $scheduledTimeFrame = 4; // DEFAULT_MAX_HOURS
        }
        $tosub = new DateInterval('PT'.$scheduledTimeFrame.'H');

        return strtotime($now->sub($tosub)->format('Y-m-d H:i:s'));
    }

    /**
     * Get the end criteria for the scheduled datetime.
     */
    private function get_end_date_scheduled_time()
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        // remove just 1 hour so normal transactions can still be processed
        $tosub = new DateInterval('PT'. 1 .'H');

        return strtotime($now->sub($tosub)->format('Y-m-d H:i:s'));
    }

    /**
     * Helper methods to create payment request.
     */
    private function getPaymentRequest(WC_Order $order)
    {
        $request = new CreatePaymentRequest();

        $customerId = get_current_user_id();
        $voucherlyCustomerId = get_user_meta($customerId, $this->getVoucherlyCustomerUserMetaKey(), true);
        if (isset($voucherlyCustomerId) && !empty($voucherlyCustomerId)) {
            $paymentTokenKey = 'wc-'.$this->id.'-payment-token';
            if (isset($_POST[$paymentTokenKey]) && 'new' !== $_POST[$paymentTokenKey]) {
                $paymentToken = sanitize_text_field($_POST[$paymentTokenKey]);
                if (is_numeric($paymentToken)) {
                    $customerPaymentMethods = $this->getCustomerPaymentMethods($customerId);
                    if (count($customerPaymentMethods) > $paymentToken) {
                        $paymentToken = $customerPaymentMethods[$paymentToken]->id;
                    }
                }
                $request->customerPaymentMethodId = $paymentToken;
            }

            $request->customerId = $voucherlyCustomerId;
        }

        $request->customerFirstName = $order->get_billing_first_name();
        $request->customerLastName = $order->get_billing_last_name();
        $request->customerEmail = $order->get_billing_email();

        $apiUrl = WC()->api_request_url('WC_Gateway_Voucherly');

        // orderId passed by session
        $redirectUrl = add_query_arg(
            [
                'action' => 'redirect',
            ],
            $apiUrl
        );
        $request->redirectOkUrl = $redirectUrl;
        $request->redirectKoUrl = $redirectUrl;

        $callbackUrl = add_query_arg(
            [
                'action' => 'callback',
            ],
            $apiUrl
        );
        $request->callbackUrl = $callbackUrl;

        $request->shippingAddress = $order->get_formatted_billing_address();
        $request->country = $order->get_billing_country();
        $request->language = explode('_', get_locale())[0];

        $request->metadata = [
            'orderId' => (string) $order->get_id(),
        ];

        $request->lines = $this->getPaymentLines($order);
        $request->discounts = $this->getPaymentDiscounts();

        return $request;
    }

    private function getPaymentLines(WC_Order $order)
    {
        $lines = [];

        $foodCategoryId = $this->get_option('foodCategory');

        foreach (WC()->cart->get_cart() as $key => $item) {
            $product = $item['data'];

            $line = new CreatePaymentRequestLine();
            $line->productName = $product->get_title();
            $line->productDescription = WC()->cart->get_item_data( $item, true );
            $line->productImage = wp_get_attachment_image_src(get_post_thumbnail_id($item['product_id']), 'full')[0];
            $line->unitAmount = round($product->get_regular_price() * 100);

            $taxable = $product->is_taxable();
            $unitDiscountedPrice = $taxable ? $product->get_price_including_tax() : $product->get_price();
            $unitDiscountedAmount = round($unitDiscountedPrice * 100);
            $line->unitDiscountAmount = $line->unitAmount - $unitDiscountedAmount;
            $line->quantity = $item['quantity'];

            if (isset($foodCategoryId) && !empty($foodCategoryId)) {
                $line->isFood = in_array($foodCategoryId, $product->get_category_ids(), true);
            } else {
                $line->isFood = true;
            }

            $line->taxRate = $this->calculateTaxRate($item['line_tax'], $item['line_total']);

            $lines[] = $line;
        }

        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

        foreach (WC()->shipping()->get_packages() as $package_key => $package) {
            if (!isset($package['rates']) && empty($package['rates'])) {
                continue;
            }
            // Get the chosen shipping method for this package
            $chosen_method_id = isset($chosen_shipping_methods[$package_key])
                ? $chosen_shipping_methods[$package_key]
                : '';

            if ($chosen_method_id && isset($package['rates'][$chosen_method_id])) {
                $shipping_method = $package['rates'][$chosen_method_id];

                $shippingTaxAmount = $shipping_method->get_shipping_tax();
                $shippingNetAmount = $shipping_method->get_cost();

                $shipping = new CreatePaymentRequestLine();
                $shipping->productName = $shipping_method->get_label();
                $shipping->unitAmount = round(($shippingTaxAmount + $shippingNetAmount) * 100);
                $shipping->quantity = 1;
                $shipping->isFood = 'yes' === $this->get_option('shippingAsFood');
                $shipping->taxRate = $this->calculateTaxRate($shippingTaxAmount, $shippingNetAmount);

                $lines[] = $shipping;
            }
        }

        return $lines;
    }

    private function getPaymentDiscounts()
    {
        $discounts = [];

        $coupons = WC()->cart->get_applied_coupons();

        foreach ($coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            $discountAmount = WC()->cart->get_coupon_discount_amount($coupon_code, false);

            $discount = new CreatePaymentRequestDiscount();
            $discount->discountName = $coupon->get_code();
            $discount->discountDescription = $coupon->get_description();
            $discount->amount = round($discountAmount * 100);

            $discounts[] = $discount;
        }

        return $discounts;
    }

    private function calculateTaxRate($taxAmount, $netAmount)
    {
        if (0 === $netAmount || 0 === $taxAmount) {
            return 0.0;
        }

        return round($taxAmount / $netAmount * 100, 2);
    }

    private function getVoucherlyCustomerUserMetaKey(): string
    {
        return 'yes' === 'voucherly_customer_'.$this->get_option('sandbox') ? 'sand' : 'live';
    }
}
