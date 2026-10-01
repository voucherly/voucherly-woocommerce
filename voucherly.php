<?php

use VoucherlyApi\Enum\LineType;
use VoucherlyApi\Enum\PaymentMode;
use VoucherlyApi\Enum\PaymentStatus;
use VoucherlyApi\Exception\ApiException;
use VoucherlyApi\Model\Payment;
use VoucherlyApi\Model\PaymentDiscount;
use VoucherlyApi\Request\CreatePaymentRequest;
use VoucherlyApi\Request\ListCustomerPaymentMethodParams;
use VoucherlyApi\Request\PaymentLineRequest;
use VoucherlyApi\Request\PaymentLineRequestProduct;
use VoucherlyApi\VoucherlyClient;

defined('ABSPATH') || exit;

require_once __DIR__.'/vendor/autoload.php';

class voucherly extends WC_Payment_Gateway
{
    public const TITLE = 'Carte di debito o credito, buoni pasto e altri metodi — Paga con Voucherly';
    public const DESCRIPTION = 'Verrai reindirizzato al portale di Voucherly dove potrai pagare con i tuoi buoni pasto o con carta di credito.';
    public const SUPPORTS = [
        'products',
        'refunds',
        // 'tokenization' // La tokenizzazione comporta la gestione dei metodi di pagamento a db. https://developer.woocommerce.com/docs/woocommerce-payment-token-api/
    ];

    private ?VoucherlyClient $voucherlyClient = null;

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

        try {
            $payment = $this->getVoucherlyClient()->payments->create($request);
        } catch (Exception $e) {
            $this->logError('Order id - '.$order->get_id().' - Could not create the payment: '.$e->getMessage());

            // WooCommerce shows the message of the exception to the customer.
            throw $e;
        }

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
            $response = $this->getVoucherlyClient()->payments->refund($order->get_transaction_id());

            return in_array($response->status, [PaymentStatus::REFUNDED, PaymentStatus::CANCELLED], true);
        } catch (Exception $e) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error(
                    'Order id - '.$order->get_id().' - Refund failed: '.$e->getMessage(),
                    ['source' => 'voucherly']
                );
            }
        }

        return false;
    }

    public function gateway_api()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Voucherly calls this endpoint from its hosted checkout and servers, so no WordPress nonce can exist; every payment is verified through the Voucherly API instead.
        if (!isset($_GET['action'])) {
            exit;
        }

        switch (sanitize_key(wp_unslash($_GET['action']))) {
            case 'redirect':
                if (!isset($_GET['success']) || !isset($_GET['status'])) {
                    header('Location: '.wc_get_checkout_url());

                    exit;
                }

                $success = sanitize_text_field(wp_unslash($_GET['success']));
                $status = sanitize_text_field(wp_unslash($_GET['status']));

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

                try {
                    $payment = $this->getVoucherlyClient()->payments->retrieve($paymentId);
                } catch (Exception $e) {
                    $this->logError('Payment '.$paymentId.' - Could not verify the payment on return from the checkout: '.$e->getMessage());

                    // The order is left as it is: the callback or the finalize cron settles it once Voucherly answers again.
                    header('Location: '.('OK' === $success ? $this->get_return_url('') : wc_get_checkout_url()));

                    exit;
                }

                // A card saved during this payment must show up at the next checkout.
                if (!empty($payment->customerId)) {
                    delete_transient($this->getCustomerPaymentMethodsCacheKey($payment->customerId));
                }

                $order = new WC_Order($payment->metadata['orderId']);
                if (self::isPaidOrConfirmed($payment)) {
                    header('Location: '.$this->get_return_url($order));

                    exit;
                }

                // $this->warning[] = $this->l('An error occurred during the operation. Don\'t worry, the payment has already been reversed. If you need any assistance, please contact customer service.');

                if ($order->has_status(['pending'])) {
                    if ('Voided' === $status) {
                        $order->update_status('cancelled', 'Payment cancelled by user');
                    } else {
                        $order->update_status('cancelled', 'Payment failed');
                    }
                }

                header('Location: '.wc_get_checkout_url());

                break;

            case 'callback':
                $rawBody = file_get_contents('php://input');
                $params = json_decode($rawBody, true);
                if (JSON_ERROR_NONE !== json_last_error() || !isset($params['id'])) {
                    exit('Invalid JSON body');
                }

                $paymentId = $params['id'];

                try {
                    $payment = $this->getVoucherlyClient()->payments->retrieve($paymentId);
                } catch (Exception $e) {
                    $this->logError('Payment '.$paymentId.' - Callback could not retrieve the payment: '.$e->getMessage());
                    header('Content-Type: application/json');

                    // ok:false makes Voucherly send the callback again.
                    exit(
                        wp_json_encode(
                            [
                                'ok' => false,
                                'error' => 'Could not retrieve the payment from Voucherly',
                            ]
                        )
                    );
                }

                if (!self::isPaidOrConfirmed($payment)) {
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

                if (PaymentMode::PAYMENT !== $payment->mode) {
                    header('Content-Type: application/json');

                    exit(
                        wp_json_encode(
                            [
                                'ok' => true,
                            ]
                        )
                    );
                }

                $orderId = $payment->metadata['orderId'];

                header('Content-Type: application/json');

                // Voucherly retries the callback when the response is not the expected one, so the same payment can arrive more than once, even concurrently.
                if (!$this->lockOrder($orderId)) {
                    exit(
                        wp_json_encode(
                            [
                                'ok' => false,
                                'error' => 'WooCommerce order is being processed by another request',
                            ]
                        )
                    );
                }

                $order = new WC_Order($orderId);

                if (!$order->has_status(wc_get_is_paid_statuses())) {
                    $order->payment_complete($paymentId);
                    $response = [
                        'ok' => true,
                        'orderId' => $orderId,
                    ];
                } elseif ($order->get_transaction_id() === $paymentId) {
                    $response = [
                        'ok' => true,
                        'orderId' => $orderId,
                    ];
                } else {
                    $response = [
                        'ok' => false,
                        'stop' => true,
                        'error' => 'WooCommerce order already paid with different payment method',
                    ];
                }

                $this->unlockOrder($orderId);

                exit(wp_json_encode($response));
        }
        // phpcs:enable
    }

    public function admin_options()
    {
        try {
            $ok = $this->isApiKeyValid($this->getApiKey());
        } catch (Exception $e) {
            $this->logError('Could not verify the API key: '.$e->getMessage());
            echo '<div class="notice-error notice">';
            echo '<p>'.esc_html($this->getUnreachableMessage($e)).'</p>';
            echo '</div>';

            return parent::admin_options();
        }

        if (!$ok) {
            echo '<div class="notice-error notice">';
            // translators: %s is replaced with Voucherly Dashboard link
            echo '<p>'.wp_kses(sprintf(__('Voucherly is not correctly configured, get an API key in developer section on <a href="%s" target="_blank">Voucherly Dashboard</a>.', 'voucherly'), 'https://dashboard.voucherly.it'), ['a' => ['href' => [], 'target' => []]]).'</p>';
            echo '</div>';
        }

        return parent::admin_options();
    }

    public function process_admin_options()
    {
        try {
            $liveOk = $this->processApiKey('live');
            if (!$liveOk) {
                $this->addInvalidApiKeyError('API key live');

                return false;
            }

            $sandOk = $this->processApiKey('sand');
            if (!$sandOk) {
                $this->addInvalidApiKeyError('API key sandbox');

                return false;
            }
        } catch (Exception $e) {
            $this->logError('Could not verify the API key: '.$e->getMessage());
            WC_Admin_Settings::add_error($this->getUnreachableMessage($e));

            return false;
        }

        parent::process_admin_options();

        // The saved settings can switch between the sandbox and the live key.
        $this->voucherlyClient = null;

        try {
            $this->getAndUpdatePaymentGateways();
        } catch (Exception $e) {
            $this->logError('Could not update the payment gateways: '.$e->getMessage());
            WC_Admin_Settings::add_error($this->getUnreachableMessage($e));
        }
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
        wp_register_style('voucherly_styles', plugins_url('/assets/css/voucherly-styles.css', __FILE__), [], $this->getPluginVersion());
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
        $gateways = self::getCheckoutGateways($this->get_option('gateways'));
        if (empty($gateways)) {
            return '';
        }

        $icon_html = '<div class="voucherly_icons">';
        foreach ($gateways as $i) {
            $icon_html .= $this->getIconHtml($i->src, $i->name);
        }
        $icon_html .= '</div>';

        // $icon_html .= sprintf( '<a href="%1$s" class="about_voucherly" onclick="javascript:window.open(\'%1$s\',\'Voucherly\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__( 'Che cosa è Voucherly?', 'voucherly' ) . '</a>', "https://voucherly.it" );

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core filter.
        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    public static function getCheckoutGateways(?string $gatewaysJson): array
    {
        $gateways = json_decode($gatewaysJson ?? '');
        if (!is_array($gateways)) {
            return [];
        }

        // Manual payments and merchant-defined methods exist on Voucherly but must not be advertised at checkout.
        return array_values(array_filter($gateways, static function ($gateway) {
            return !in_array($gateway->type ?? '', ['Hidden', 'Custom'], true);
        }));
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

                        $payment = $this->getVoucherlyClient()->payments->retrieve($transactionId);
                        if ($order->has_status(wc_get_is_paid_statuses())) {
                            continue;
                        }

                        if (self::isPaidOrConfirmed($payment)) {
                            $order->payment_complete($payment->id);
                            $order->add_order_note('The Voucherly Payment has been finalized by custom cron action');
                            $order->save();

                            continue;
                        }

                        // None of these statuses can turn into a paid one.
                        if (in_array($payment->status, [PaymentStatus::CANCELLED, PaymentStatus::VOIDED, PaymentStatus::EXPIRED], true)) {
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

            if ((int) $card->expirationYear * 100 + (int) $card->expirationMonth < (int) gmdate('Ym')) {
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
            echo wp_kses_post($this->getIconHtml(plugins_url($brandImagePath, __FILE__), $card->brand));
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

    private function getApiKey(): string
    {
        return (string) $this->get_option('yes' === $this->get_option('sandbox') ? 'apiKey_sand' : 'apiKey_live');
    }

    private function getVoucherlyClient(): VoucherlyClient
    {
        if (null === $this->voucherlyClient) {
            $this->voucherlyClient = $this->createVoucherlyClient($this->getApiKey());
        }

        return $this->voucherlyClient;
    }

    private function createVoucherlyClient(string $apiKey): VoucherlyClient
    {
        return new VoucherlyClient([
            'apiKey' => $apiKey,
            'os' => 'WordPress',
            'osVersion' => get_bloginfo('version'),
            'osFramework' => 'WooCommerce '.WC()->version,
            'app' => 'voucherly-woocommerce',
            'appVersion' => $this->getPluginVersion(),
            'appHouse' => 'Voucherly',
            'deviceType' => 'ECOMMERCE-PLUGIN',
        ]);
    }

    private function isApiKeyValid(string $apiKey): bool
    {
        // VoucherlyClient rejects an empty key, which the API would refuse with 401 anyway.
        if ('' === $apiKey) {
            return false;
        }

        try {
            $this->createVoucherlyClient($apiKey)->paymentGateways->list();
        } catch (ApiException $e) {
            return 401 !== $e->getStatusCode();
        }

        return true;
    }

    private static function isPaidOrConfirmed(Payment $payment): bool
    {
        return in_array($payment->status, [PaymentStatus::PAID, PaymentStatus::CONFIRMED], true);
    }

    private function processApiKey($environment): bool
    {
        $optionKey = 'apiKey_'.$environment;

        $apiKey = $this->get_option($optionKey);
        $newApiKey = $this->get_post_data()['woocommerce_voucherly_'.$optionKey];

        if (!empty($newApiKey)) {
            $ok = $this->isApiKeyValid($newApiKey);
            if (!$ok) {
                return false;
            }
        }

        $this->update_option($optionKey, $newApiKey);

        // Should I delete user metadata?

        return true;
    }

    private function getUnreachableMessage(Exception $e): string
    {
        // translators: %s is replaced with the error returned by the Voucherly API or by the connection
        return sprintf(__('Voucherly could not be reached: %s', 'voucherly'), $e->getMessage());
    }

    private function logError(string $message)
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($message, ['source' => 'voucherly']);
        }
    }

    private function addInvalidApiKeyError($name)
    {
        // Settings are saved before the admin page starts its output, so the error goes through WooCommerce instead of being echoed.
        // translators: %s is replaced with form label (API key)
        WC_Admin_Settings::add_error(sprintf(__('The "%s" is invalid', 'voucherly'), $name));
    }

    private function getAndUpdatePaymentGateways()
    {
        $gateways = $this->getPaymentGateways();
        $this->update_option('gateways', wp_json_encode($gateways));
    }

    private function getPaymentGateways()
    {
        $paymentGateways = $this->getVoucherlyClient()->paymentGateways->list()->items ?? [];
        $gateways = [];

        foreach ($paymentGateways as $gateway) {
            if ($gateway->isActive && !$gateway->merchantConfiguration->isFallback) {
                $formattedGateway['id'] = $gateway->id;
                $formattedGateway['name'] = $gateway->name;
                $formattedGateway['type'] = $gateway->type;
                $formattedGateway['src'] = $gateway->icon ?? $gateway->checkoutImage;

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

        $key = $this->getCustomerPaymentMethodsCacheKey($voucherlyCustomerId);
        $customerPaymentMethods = get_transient($key);
        if (false === $customerPaymentMethods) {
            try {
                $params = new ListCustomerPaymentMethodParams();
                $params->length = 100;
                $customerPaymentMethods = $this->getVoucherlyClient()->paymentMethods->list($voucherlyCustomerId, $params)->items;
            } catch (Exception $e) {
                // The customer can still pay with a new method, so a failed lookup must not break the checkout; it is not cached, so the next page load tries again.
                $this->logError('Could not load the payment methods of customer '.$voucherlyCustomerId.': '.$e->getMessage());

                return [];
            }
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

    private function getPluginVersion()
    {
        return get_plugin_data(__DIR__.'/woocommerce-gateway-voucherly.php', false, false)['Version'];
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
        $request->mode = PaymentMode::PAYMENT;

        $customerId = get_current_user_id();
        $voucherlyCustomerId = get_user_meta($customerId, $this->getVoucherlyCustomerUserMetaKey(), true);
        if (isset($voucherlyCustomerId) && !empty($voucherlyCustomerId)) {
            $paymentTokenKey = 'wc-'.$this->id.'-payment-token';
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before calling process_payment().
            if (isset($_POST[$paymentTokenKey]) && 'new' !== $_POST[$paymentTokenKey]) {
                $paymentToken = sanitize_text_field(wp_unslash($_POST[$paymentTokenKey]));
                // phpcs:enable
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

            $lineProduct = new PaymentLineRequestProduct();
            $lineProduct->externalId = (string) $product->get_id();
            $lineProduct->name = $product->get_title();
            $lineProduct->variant = wc_get_formatted_cart_item_data($item, true);
            $lineProduct->image = (string) wp_get_attachment_image_url(get_post_thumbnail_id($item['product_id']), 'full');

            $line = new PaymentLineRequest();
            $line->unitAmount = round($product->get_regular_price() * 100);

            $taxable = $product->is_taxable();
            $unitDiscountedPrice = $taxable ? wc_get_price_including_tax($product) : $product->get_price();
            $unitDiscountedAmount = round($unitDiscountedPrice * 100);
            $line->unitDiscountAmount = $line->unitAmount - $unitDiscountedAmount;
            $line->quantity = $item['quantity'];

            if (isset($foodCategoryId) && !empty($foodCategoryId)) {
                // The option is a string and the term ids are integers; a variation has no categories of its own, so they are read from the parent product.
                $isFood = in_array((int) $foodCategoryId, wc_get_product_term_ids($item['product_id'], 'product_cat'), true);
            } else {
                $isFood = true;
            }
            $lineProduct->lineType = $isFood ? LineType::FOOD : LineType::NON_FOOD;

            $lineProduct->taxRate = $this->calculateTaxRate($item['line_tax'], $item['line_total']);

            $line->product = $lineProduct;

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

                $shippingProduct = new PaymentLineRequestProduct();
                $shippingProduct->externalId = 'shipping_'.$shipping_method->get_id();
                $shippingProduct->name = $shipping_method->get_label();
                $shippingProduct->lineType = 'yes' === $this->get_option('shippingAsFood') ? LineType::FOOD : LineType::SHIPPING;
                $shippingProduct->taxRate = $this->calculateTaxRate($shippingTaxAmount, $shippingNetAmount);

                $shipping = new PaymentLineRequest();
                $shipping->unitAmount = round(($shippingTaxAmount + $shippingNetAmount) * 100);
                $shipping->quantity = 1;
                $shipping->product = $shippingProduct;

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

            $discount = new PaymentDiscount();
            $discount->discountName = $coupon->get_code();
            $discount->discountDescription = $coupon->get_description();
            $discount->amount = round($discountAmount * 100);

            $discounts[] = $discount;
        }

        return $discounts;
    }

    private function calculateTaxRate(float $taxAmount, float $netAmount)
    {
        // I don't need to test the type. NetAmount is probably 0.0
        if (0.0 === $netAmount || 0.0 === $taxAmount) {
            return 0.0;
        }

        return round($taxAmount / $netAmount * 100, 2);
    }

    private function getVoucherlyCustomerUserMetaKey(): string
    {
        return 'voucherly_customer_'.('yes' === $this->get_option('sandbox') ? 'sand' : 'live');
    }

    private function getCustomerPaymentMethodsCacheKey(string $voucherlyCustomerId): string
    {
        return 'voucherly_payment_methods:'.$voucherlyCustomerId;
    }

    private function lockOrder($orderId): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $this->getOrderLockName($orderId), 10));

        // Only a timeout means another request holds the lock; databases without GET_LOCK keep the previous unlocked behaviour instead of rejecting every callback.
        return '0' !== $acquired;
    }

    private function unlockOrder($orderId)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->getOrderLockName($orderId)));
    }

    private function getOrderLockName($orderId): string
    {
        global $wpdb;

        // MySQL named locks are shared by every database on the server and limited to 64 characters.
        return 'voucherly_order_'.md5(DB_NAME.$wpdb->prefix.$orderId);
    }
}
