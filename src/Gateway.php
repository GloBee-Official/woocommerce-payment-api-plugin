<?php

namespace GloBee\WooCommerce;

use GloBee\PaymentApi\Connectors\GloBeeCurlConnector;
use GloBee\PaymentApi\Exceptions\Http\AuthenticationException;
use GloBee\PaymentApi\Exceptions\Validation\ValidationException;
use GloBee\PaymentApi\Models\PaymentRequest;
use GloBee\PaymentApi\PaymentApi;

/**
 * Gateway class
 */
class Gateway extends \WC_Payment_Gateway
{
    public static $log = false;

    protected $order_states;

    protected $network;

    protected $payment_api_key;

    /** @var PaymentApi */
    protected $payment_api;

    public function __construct()
    {
        $this->id = 'globee';
        $this->icon = plugin_dir_url(__FILE__).'assets/images/icon.png';
        $this->has_fields = false;
        $this->method_title = 'GloBee';
        $this->method_description = 'GloBee allows you to accept cryptocurrency payments on your WooCommerce store';
        $this->order_button_text = __('Proceed to GloBee', 'globee');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->order_states = $this->get_option('globee_woocommerce_order_states');

        $this->network = $this->get_option('globee_network');
        $this->payment_api_key = $this->get_option('payment_api_key');

        // Save settings
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);

        // Save Order States
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'save_order_states']);

        // Save IPN Callback
        add_action('woocommerce_api_globee_ipn_callback', [$this, 'ipn_callback']);

        if (empty($_POST)) {
            add_action('admin_notices', [$this, 'validate_api_key']);
        }
    }

    public function validate_api_key()
    {
        if (empty($this->payment_api_key)) {
            $this->display_globee_errors(
                '<span style="font-size: 1.4em;">Please add your GloBee API key to enable payments through GloBee.</span>'
            );

            return;
        }
        $paymentApi = $this->get_payment_api();
        try {
            $paymentApi->getAccount();
        } catch (AuthenticationException $e) {
            $this->display_globee_errors(
                '<span style="font-size: 1.4em;">Warning: There has been a problem authenticating with the GloBee API.'
                .' Please check that your key is correct and that you are using the correct Network.</span>'
            );
        } catch (\Exception $e) {
            $this->display_globee_errors(
                'There has been a problem connecting to the GloBee API. Please make sure all your settings are correct.'
            );
        }
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable GloBee', 'globee'),
                'type' => 'checkbox',
                'label' => __('Enable users to select GloBee as a payment option on the checkout page', 'globee'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'globee'),
                'type' => 'text',
                'description' => __('The name users will see on the checkout page', 'globee'),
                'default' => __('GloBee.com', 'globee'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Customer Message', 'globee'),
                'type' => 'textarea',
                'description' => __(
                    'The message explaining to the user what will happen, after selecting GloBee as their payment option',
                    'globee'
                ),
                'default' => 'You will be redirected to GloBee.com to complete your purchase.',
                'desc_tip' => true,
            ],
            'payment_api_key' => [
                'title' => __('Payment API Key', 'globee'),
                'type' => 'payment_api_key',
                'description' => __(
                    'Your Payment API Key. You can find this key on the Payment API page of your account on globee.com',
                    'globee'
                ),
                'default' => $this->payment_api_key ?: '',
                'desc_tip' => true,
            ],
            'network' => [
                'title' => __('Network', 'globee'),
                'type' => 'select',
                'description' => __(
                    'Choose if you want to use the GloBee Livenet or Testnet. '
                    .'Testnet is for testing purposes and require a api key from the test system.',
                    'globee'
                ),
                'default' => 'livenet',
                'options' => [
                    'livenet' => 'Livenet',
                    'testnet' => 'Testnet',
                ],
                'desc_tip' => true,
            ],
            'order_states' => array(
                'type' => 'order_states',
            ),
            'transaction_speed' => [
                'title' => __('Transaction Speed', 'globee'),
                'type' => 'select',
                'description' => __(
                    'Choose your preferred transaction speed. View your Settlement Settings page on GloBee for more details.',
                    'globee'
                ),
                'default' => 'medium',
                'options' => [
                    'high' => 'High',
                    'medium' => 'Medium',
                    'low' => 'Low',
                ],
                'desc_tip' => true,
            ],
            'notification_url' => [
                'title' => __('Notification URL', 'globee'),
                'type' => 'url',
                'description' => __(
                    'GloBee will send IPNs for orders to this URL with the GloBee payment request data',
                    'globee'
                ),
                'default' => WC()->api_request_url('globee_ipn_callback'),
                'placeholder' => WC()->api_request_url('globee_ipn_callback'),
                'desc_tip' => true,
            ],
            'redirect_url' => [
                'title' => __('Redirect URL', 'globee'),
                'type' => 'url',
                'description' => __(
                    'After paying the GloBee invoice, users will be redirected back to this URL',
                    'globee'
                ),
                'default' => $this->get_return_url(),
                'placeholder' => $this->get_return_url(),
                'desc_tip' => true,
            ],
            'support_details' => [
                'title' => __('Support & Version Information', 'globee'),
                'type' => 'title',
                'description' => sprintf(
                    __(
                        '<b>Versions</b><br/>Plugin version: %s<br/>PHP version: %s<br/><br/><b>Support</b><br/>'
                        .'If you need assistance, please contact support@globee.com with your version numbers. '
                        .'Thank you for using GloBee!',
                        'globee'
                    ),
                    get_option('globee_woocommerce_version', '2.0.0'),
                    PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION
                ),
            ],
        ];
    }

    public function generate_order_states_html()
    {
        $globeeStatuses = [
            'new' => 'New Order',
            'paid' => 'Paid',
            'confirmed' => 'Confirmed',
            'complete' => 'Complete',
            'refunded' => 'Refunded',
            'invalid' => 'Invalid',
        ];

        $statuses = [
            'new' => 'wc-pending-payment',
            'paid' => 'wc-processing',
            'confirmed' => 'wc-processing',
            'complete' => 'wc-processing',
            'refunded' => 'wc-refunded',
            'invalid' => 'wc-failed',
        ];

        $wcStatuses = wc_get_order_statuses();

        return View::make('order_states', compact('globeeStatuses', 'statuses', 'wcStatuses'));
    }

    public function generate_payment_api_key_html($key, $data)
    {
        $style = 'color: red; font-weight: 600;';
        unset($data['type']);
        if (empty($this->payment_api_key)) {
            $message =  'Please enter a valid API key for the selected network.';
        } else {
            $paymentApi = $this->get_payment_api();
            try {
                $account = $paymentApi->getAccount();
                $message = 'Authenticated as: '.$account->name;
                $style = 'color: green';
            } catch (AuthenticationException $e) {
                $message = 'Couldn\'t authenticate using the supplied API key.';
                $message .= ' Please make sure the key is valid and that you have selected the correct network.';
            } catch (\Exception $e) {
                $message = 'Unable to communicate with the GloBee server: '.get_class($e);
            }
        }
        $html = '<tr><th>Authentication:</th><td style="'.$style.'">'.$message.'</td></tr>';
        return $html.parent::generate_text_html($key, $data);
    }

    public function save_order_states()
    {
        $globeeStatuses = [
            'new' => 'New Order',
            'paid' => 'Paid',
            'confirmed' => 'Confirmed',
            'complete' => 'Complete',
            'invalid' => 'Invalid',
        ];

        $wcStatuses = wc_get_order_statuses();

        if (true === isset($_POST['globee_woocommerce_order_states'])) {
            $orderStates = get_option('globee_woocommerce_order_states');
            foreach ($globeeStatuses as $globeeState => $globeeName) {
                if (false === isset($_POST['globee_woocommerce_order_states'][$globeeState])) {
                    continue;
                }
                $wcState = $_POST['globee_woocommerce_order_states'][$globeeState];
                if (true === array_key_exists($wcState, $wcStatuses)) {
                    $orderStates[$globeeState] = $wcState;
                }
            }
            update_option('globee_woocommerce_order_states', $orderStates);
        }
    }

    public function validate_order_states_field()
    {
        $orderStates = $this->get_option('globee_woocommerce_order_states');
        if (isset($_POST[$this->id.$this->plugin_id.'_order_states'])) {
            $orderStates = $_POST[$this->id.$this->plugin_id.'_order_states'];
        }

        return $orderStates;
    }

    public function validate_url_field($key)
    {
        $url = $this->get_option($key);
        if (isset($_POST[$this->plugin_id.$this->id.'_'.$key])) {
            if (filter_var($_POST[$this->plugin_id.$this->id.'_'.$key], FILTER_VALIDATE_URL) !== false) {
                return $_POST[$this->plugin_id.$this->id.'_'.$key];
            }

            return '';
        }

        return $url;
    }

    public function thankyou_page($orderId)
    {
    }

    public function process_payment($order_id)
    {
        $this->log('Processing Order ID: '.$order_id);

        if (true === empty($order_id)) {
            throw new \Exception(
                'The GloBee payment plugin was called to process a payment but the '
                .'orderId was missing.'
            );
        }

        $order = wc_get_order($order_id);
        if (false === $order) {
            throw new \Exception(
                'The GloBee payment plugin was called to process a payment but could '
                .'not retrieve the order details for order_id '.$order_id.'.'
            );
        }

        // Mark new order according to user settings (we're awaiting the payment)
        $newOrderStatus = get_option('globee_woocommerce_order_states')['new'];
        $order->update_status($newOrderStatus, 'Awaiting payment notification from GloBee.');

        $paymentApi = $this->get_payment_api();
        $paymentRequest = new PaymentRequest();
        $paymentRequest->successUrl = $this->get_option('redirect_url', $this->get_return_url());
        $paymentRequest->ipnUrl = $this->get_option(
            'notification_url',
            WC()->api_request_url('globee_ipn_callback')
        );
        $paymentRequest->currency = get_woocommerce_currency();
        $paymentRequest->confirmationSpeed = $this->get_option('transaction_speed', 'medium');
        $paymentRequest->total = $order->calculate_totals();
        $paymentRequest->customerEmail = $order->get_billing_email();
        $paymentRequest->customPaymentId = $order_id;
        try {
            $response = $paymentApi->createPaymentRequest($paymentRequest);
        } catch (ValidationException $e) {
            $errors = '';
            foreach ($e->getErrors() as $error) {
                $errors .= $error['message']."<br/>";
            }
            wc_add_notice($errors, 'error');

            return;
        }

        $redirectUrl = $response->redirectUrl; // Redirect your client to this URL to make payment

        WC()->cart->empty_cart();

        // Redirect the customer to the globee invoice
        return [
            'result' => 'success',
            'redirect' => $redirectUrl,
        ];
    }

    public function log($message, $level = 'info', $source = null)
    {
        error_log($message);
        if ($source == null) {
            $source = 'globee_woocommerce';
        }

        if (empty(self::$log)) {
            self::$log = wc_get_logger();
        }
        self::$log->log($level, $message, array('source' => $source));
    }

    public function ipn_callback()
    {
        // Retrieve the Invoice ID and Network URL from the supposed IPN data
        $post = file_get_contents('php://input');

        $json = json_decode($post, true);
        if ($json === null || !isset($json['id'])) {
            $this->throwException('GloBee plugin received an invalid JSON payload sent to IPN handler: '.$post);
        }
        $paymentRequest = PaymentRequest::fromResponse($json);

        $orderId = $paymentRequest->customPaymentId;
        $this->log('Processing Callback for Order ID: '.$orderId);
        if (true === empty($orderId)) {
            $this->throwException('The GloBee payment plugin was called to process an IPN message but no order ID was set.');
        }

        $order = wc_get_order($orderId);
        $current_status = $order->get_status();
        $orderStates = get_option('globee_woocommerce_order_states');
        $paid_status = $orderStates['paid'];
        $confirmed_status = $orderStates['confirmed'];
        $complete_status = $orderStates['complete'];
        $invalid_status = $orderStates['invalid'];

        $paymentApi = $this->get_payment_api();
        $paymentRequest = $paymentApi->getPaymentRequest($json['id']);
        if ($paymentRequest->customPaymentId != $orderId) {
            $this->throwException(
                'Trying to update an order where the order ID does not match the custom payment ID from the GloBee Payment Request.'
            );
        }

        switch ($paymentRequest->status) {
            case 'paid':
                if (!($current_status == $complete_status || 'wc_'.$current_status == $complete_status || $current_status == 'completed')) {
                    $order->update_status($paid_status);
                    $order->add_order_note(
                        __(
                            'GloBee payment paid. Awaiting network confirmation and payment '
                            .'completed status.',
                            'globee'
                        )
                    );
                }
                break;

            case 'confirmed':
                if (!($current_status == $complete_status || 'wc_'.$current_status == $complete_status || $current_status == 'completed')) {
                    $order->update_status($confirmed_status);
                    $order->add_order_note(
                        __(
                            'GloBee payment confirmed. Awaiting payment completed status.',
                            'globee'
                        )
                    );
                }
                break;

            case 'completed':
                if (!($current_status == $complete_status || 'wc_'.$current_status == $complete_status || $current_status == 'completed')) {
                    $order->payment_complete();
                    $order->update_status($complete_status);
                    $order->add_order_note(
                        __(
                            'GloBee payment completed. Payment credited to your merchant '
                            .'account.',
                            'globee'
                        )
                    );
                }
                break;

            case 'invalid':
                if (!($current_status == $complete_status || 'wc_'.$current_status == $complete_status || $current_status == 'completed')) {
                    $order->update_status(
                        $invalid_status,
                        __(
                            'Payment is invalid for this order! The '
                            .'payment was not confirmed by the network within 1 hour. Do not ship the product for '
                            .'this order!',
                            'globee'
                        )
                    );
                }
                break;
        }

        $this->log("[INFO] Changed Order {$orderId}'s state from {$current_status} to {$paymentRequest->status}", 'INFO');
    }

    protected function get_payment_api()
    {
        if (!$this->payment_api) {
            global $woocommerce;
            global $wp_version;
            $connector = new GloBeeCurlConnector(
                $this->payment_api_key,
                $this->get_option('network') === 'livenet',
                [
                    'WooCommerce' => $woocommerce->version,
                    'Wordpress' => $wp_version,
                ]
            );
            $this->payment_api = new PaymentApi($connector);
        }

        return $this->payment_api;
    }

    public function display_globee_errors($errors)
    {
        $errors = (array)$errors;
        echo '<div id="woocommerce_errors" class="error notice is-dismissible">';
        foreach ($errors as $error) {
            echo '<p>'.wp_kses_post($error).'</p>';
        }
        echo '</div>';
    }
    protected function throwException($message)
    {
        error_log($message);
        throw new \Exception($message);
    }
}
