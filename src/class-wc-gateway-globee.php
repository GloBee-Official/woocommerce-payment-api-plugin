<?php
/*
Plugin Name: GloBee
Plugin URI: https://globee.com/woocommerce
Description: Accepts cryptocurrency payments on your WooCommerce Shop using GloBee.
Version: 1.0.1
Author: GloBee
Author URI: https://globee.com/

License:           Copyright 2016-2017 GloBee., MIT License
License URI:       https://github.com/globee/woocommerce-payment-api-plugin/blob/master/LICENSE
GitHub Plugin URI: https://github.com/globee/woocommerce-payment-api-plugin
*/

if (false === defined('ABSPATH')) {
    exit;
}

try {
    require_once __DIR__.'/lib/Connectors/Connector.php';
    require_once __DIR__.'/lib/Connectors/CurlWrapper.php';
    require_once __DIR__.'/lib/Connectors/GloBeeCurlConnector.php';
    require_once __DIR__.'/lib/PaymentApi.php';
    require_once __DIR__.'/lib/Models/PropertyTrait.php';
    require_once __DIR__.'/lib/Models/ValidationTrait.php';
    require_once __DIR__.'/lib/Models/Model.php';
    require_once __DIR__.'/lib/Models/PaymentRequest.php';
    require_once __DIR__.'/lib/Exceptions/Http/HttpException.php';
    require_once __DIR__.'/lib/Exceptions/Http/NotFoundException.php';
    require_once __DIR__.'/lib/Exceptions/Http/AuthenticationException.php';
    require_once __DIR__.'/lib/Exceptions/Http/ForbiddenException.php';
    require_once __DIR__.'/lib/Exceptions/Http/ServerErrorException.php';
    require_once __DIR__.'/lib/Exceptions/Connectors/ConnectionException.php';
    require_once __DIR__.'/lib/Exceptions/Connectors/CurlConnectionException.php';
    require_once __DIR__.'/lib/Exceptions/Validation/ValidationException.php';
    require_once __DIR__.'/lib/Exceptions/Validation/BelowMinimumException.php';
    require_once __DIR__.'/lib/Exceptions/Validation/InvalidArgumentException.php';
    require_once __DIR__.'/lib/Exceptions/Validation/InvalidEmailException.php';
    require_once __DIR__.'/lib/Exceptions/Validation/InvalidSelectionException.php';
    require_once __DIR__.'/lib/Exceptions/Validation/InvalidUrlException.php';
} catch (\Exception $exception) {
    throw new \Exception(
        'The PaymentAPI plugin was not installed correctly or the files are corrupt. Please reinstall the plugin. If this message persists after a reinstall, contact support@globee.com with this message.'
    );
}

add_action('plugins_loaded', 'globee_woocommerce_init', 0);
register_activation_hook(__FILE__, 'globee_woocommerce_activate');

function globee_woocommerce_init()
{
    if (true === class_exists('WC_Gateway_GloBee')) {
        return;
    }

    if (false === class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-gateway-globee', false, dirname(plugin_basename(__FILE__)).'/languages');

    /**
     * Gateway class
     */
    class WC_Gateway_GloBee extends WC_Payment_Gateway
    {
        public static $log = false;

        public function __construct()
        {
            $this->id = 'globee';
            $this->plugin_id = 'woocommerce';
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
            add_action('woocommerce_api_wc_gateway_globee', [$this, 'ipn_callback']);
        }

        public function __destruct()
        {

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
                    'type' => 'text',
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
                    'default' => WC()->api_request_url('WC_Gateway_GloBee'),
                    'placeholder' => WC()->api_request_url('WC_Gateway_GloBee'),
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
                        get_option('globee_woocommerce_version', '1.0.1'),
                        PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION
                    ),
                ],
            ];
        }

        public function generate_order_states_html()
        {
            ob_start();

            $globeeStatuses = [
                'new' => 'New Order',
                'paid' => 'Paid',
                'confirmed' => 'Confirmed',
                'complete' => 'Complete',
                'invalid' => 'Invalid',
            ];

            $statuses = [
                'new' => 'wc-on-hold',
                'paid' => 'wc-processing',
                'confirmed' => 'wc-processing',
                'complete' => 'wc-completed',
                'invalid' => 'wc-failed',
            ];

            $wcStatuses = wc_get_order_statuses();

            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">Order States:</th>
                <td class="forminp" id="globee_order_states">
                    <table cellspacing="0" cellpadding="0" style="padding:0">
                        <?php foreach ($globeeStatuses as $globeeState => $globeeName) { ?>
                            <tr>
                                <th><?php echo $globeeName; ?></th>
                                <td>
                                    <select name="globee_woocommerce_order_states[<?php echo $globeeState; ?>]"
                                            width="200">
                                        <?php
                                        $orderStates = get_option('globee_woocommerce_order_states');
                                        foreach ($wcStatuses as $wcState => $wcName) {
                                            $currentOption = $orderStates[$globeeState];
                                            if (true === empty($currentOption)) {
                                                $currentOption = $statuses[$globeeState];
                                            }
                                            echo "<option value='$wcState'";
                                            if ($currentOption === $wcState) {
                                                echo "selected";
                                            }
                                            echo ">$wcName</option>";
                                        } ?>
                                    </select>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </td>
            </tr>
            <?php

            return ob_get_clean();
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
            if (isset($_POST[$this->id.$this->plugin_id.'_'.$key])) {
                if (filter_var($_POST[$this->id.$this->plugin_id.'_'.$key], FILTER_VALIDATE_URL) !== false) {
                    return $_POST[$this->id.$this->plugin_id.'_'.$key];
                }

                return '';
            }

            return $url;
        }

        public function validate_redirect_url_field()
        {
            $redirectUrl = $this->get_option('redirect_url', '');
            if (isset($_POST['globee_woocommerce_redirect_url'])) {
                if (filter_var($_POST['globee_woocommerce_redirect_url'], FILTER_VALIDATE_URL) !== false) {
                    return $_POST['globee_woocommerce_redirect_url'];
                }

                return '';
            }

            return $redirectUrl;
        }

        public function thankyou_page($orderId)
        {
            // Do something here if you want to customize the return url page
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

            $live = true;
            if ($this->get_option('network') === 'testnet') {
                $live = false;
            }
            $connector = new \GloBee\PaymentApi\Connectors\GloBeeCurlConnector(
                $this->payment_api_key,
                $live,
                ['WooCommerce']
            );
            $paymentApi = new \GloBee\PaymentApi\PaymentApi($connector);
            $paymentRequest = new \GloBee\PaymentApi\Models\PaymentRequest();
            $paymentRequest->successUrl = $this->get_option('redirect_url', $this->get_return_url());
            $paymentRequest->ipnUrl = $this->get_option('notification_url', WC()->api_request_url('WC_Gateway_globee'));
            $paymentRequest->currency = get_woocommerce_currency();
            $paymentRequest->confirmationSpeed = $this->get_option('transaction_speed', 'medium');
            $paymentRequest->total = $order->calculate_totals();
            $paymentRequest->customerEmail = $order->get_billing_email();
            $paymentRequest->customPaymentId = $order_id;
            try {
                $response = $paymentApi->createPaymentRequest($paymentRequest);
            } catch (Exception $e) {
                $errors = '';
                foreach ($e->getErrors() as $error) {
                    $errors .= $error['message']."<br/>";
                }
                wc_add_notice($errors, 'error');

                return;
            }

            $paymentRequestId = $response->id; // Save this ID to know when payment has been made
            $redirectUrl = $response->redirectUrl; // Redirect your client to this URL to make payment

            // Redurce order stock
            wc_reduce_stock_levels($order_id);

            // Remove cart
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
            $post = file_get_contents("php://input");
            if (true === empty($post)) {
                error_log('GloBee plugin received empty POST data for an IPN message.');
                wp_die('No post data');
            }

            $json = json_decode($post, true);
            if (!isset($json['id'])) {
                error_log('GloBee plugin received an invalid JSON payload sent to IPN handler: '.$post);
                wp_die('Invalid JSON');
            }

            if (false === array_key_exists('custom_payment_id', $json)) {
                error_log(
                    'GloBee plugin did not receive a Payment ID present in JSON payload: '.var_export($json, true)
                );
                wp_die('No Custom Payment ID');
            }
            if (false === array_key_exists('status', $json)) {
                error_log('GloBee plugin did not receive a status present in JSON payload: '.var_export($json, true));
                wp_die('No Status');
            }

            $orderId = $json['custom_payment_id'];
            $this->log('Processing Callback for Order ID: '.$orderId);
            if (false === isset($orderId) && true === empty($orderId)) {
                error_log('The GloBee payment plugin was called to process an IPN message but no order ID was set.');
                throw new \Exception(
                    'The GloBee payment plugin was called to process an IPN message but no order ID was set.'
                );
            }

            $order = wc_get_order($orderId);
            if (false === $order || 'WC_Order' !== get_class($order)) {
                error_log(
                    'The GloBee payment plugin was called to process an IPN message but could not retrieve the order details for order_id '.$orderId
                );
                throw new \Exception(
                    'The GloBee payment plugin was called to process an IPN message but could not retrieve the order details for order_id '.$orderId
                );
            }

            $current_status = $order->get_status();
            if (false === isset($current_status) && true === empty($current_status)) {
                error_log(
                    'The GloBee payment plugin was called to process an IPN message but could not obtain the current status from the order.'
                );
                throw new \Exception(
                    'The GloBee payment plugin was called to process an IPN message but could not obtain the current status from the order.'
                );
            }

            $orderStates = get_option('globee_woocommerce_order_states');
            $newOrderStatus = $orderStates['new'];
            $paid_status = $orderStates['paid'];
            $confirmed_status = $orderStates['confirmed'];
            $complete_status = $orderStates['complete'];
            $invalid_status = $orderStates['invalid'];
            $status = $json['status'];
            if (false === isset($status) && true === empty($status)) {
                error_log(
                    'The GloBee payment plugin was called to process an IPN message but could not obtain the new status from the payment request.'
                );
                throw new \Exception(
                    'The GloBee payment plugin was called to process an IPN message but could not obtain the new status from the payment request.'
                );
            }

            $live = true;
            if ($this->get_option('network') === 'testnet') {
                $live = false;
            }
            $connector = new \GloBee\PaymentApi\Connectors\GloBeeCurlConnector(
                $this->payment_api_key,
                $live,
                ['WooCommerce']
            );
            $paymentApi = new \GloBee\PaymentApi\PaymentApi($connector);
            $paymentRequest = $paymentApi->getPaymentRequest($json['id']);
            if ($paymentRequest->customPaymentId != $orderId) {
                error_log(
                    'Trying to update an order where the order ID does not match the custom payment ID from the GloBee Payment Request.'
                );
                throw new \Exception(
                    'Trying to update an order where the order ID does not match the custom payment ID from the GloBee Payment Request.'
                );
            }

            switch ($status) {
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
            error_log('[INFO] Changed Order '.$orderId.'\'s state from '.$current_status.' to '.$status);

        }
    }

    /**
     * Add the Gateway to WooCommerce
     *
     * @param $methods
     * @return array
     */
    function woocommerce_add_globee_gateway($methods)
    {
        $methods[] = 'WC_Gateway_GloBee';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_globee_gateway');

    /**
     * Add Settings and Logs links to the plugin entry in the plugins menu
     *
     * @param $links
     * @param $file
     * @return mixed
     */
    function globee_action_links($links, $file)
    {
        static $thisPlugin;

        if (false === isset($thisPlugin) || true === empty($thisPlugin)) {
            $thisPlugin = plugin_basename(__FILE__);
        }

        if ($file == $thisPlugin) {
            $settingsLink = '<a href="'.get_bloginfo('wpurl')
                .'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=globee">Settings</a>';
            array_unshift($links, $settingsLink);
        }

        return $links;
    }

    add_filter('plugin_action_links', 'globee_action_links', 10, 2);
}

function globee_woocommerce_check_for_valid_system_requirements()
{
    global $wp_version;
    global $woocommerce;

    $errors = [];
    $contactYourWebAdmin = " in order to function. Please contact your web server administrator for assistance.";

    # PHP
    if (true === version_compare(PHP_VERSION, '5.4.0', '<')) {
        $errors[] = 'Your PHP version is too old. The GloBee payment plugin requires PHP 5.4 or higher'
            .$contactYourWebAdmin;
    }

    # Wordpress
    if (true === version_compare($wp_version, '4.9', '<')) {
        $errors[] = 'Your WordPress version is too old. The GloBee payment plugin requires Wordpress 4.9 or higher'
            .$contactYourWebAdmin;
    }

    # WooCommerce
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated'.$contactYourWebAdmin;

    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is '.$woocommerce->version.'. The GloBee payment plugin requires '
            .'WooCommerce 2.2 or higher'.$contactYourWebAdmin;
    }

    # OpenSSL
    if (extension_loaded('openssl') === false) {
        $errors[] = 'The GloBee payment plugin requires the OpenSSL extension for PHP'.$contactYourWebAdmin;
    }

    # GMP
    if (false === extension_loaded('gmp')) {
        $errors[] = 'The GloBee payment plugin requires the GMP extension for PHP'.$contactYourWebAdmin;
    }

    # BCMath
    if (false === extension_loaded('bcmath')) {
        $errors[] = 'The GloBee payment plugin requires the BC Math extension for PHP'.$contactYourWebAdmin;
    }

    # Curl required
    if (false === extension_loaded('curl')) {
        $errors[] = 'The GloBee payment plugin requires the Curl extension for PHP'.$contactYourWebAdmin;
    }

    if (!empty($errors)) {
        return implode("<br>\n", $errors);
    }

    return null;
}

function globee_woocommerce_activate()
{
    $plugins_url = admin_url('plugins.php');

    $errorMessages = globee_woocommerce_check_for_valid_system_requirements();

    # Activate the plugin if there is no error messages
    if (!empty($errorMessages)) {
        wp_die($errorMessages.'<br><a href="'.$plugins_url.'">Return to plugins screen</a>');
    }

    # Update the version number
    update_option('globee_woocommerce_version', '1.0.1');

    # Check if an older version of the plugin needs to be deactivated
    foreach (get_plugins() as $file => $plugin) {
        if ('GloBee Woocommerce' === $plugin['Name'] && true === is_plugin_active($file)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                'GloBee for WooCommerce requires that the old plugin, <b>GloBee Woocommerce</b> be deactivated and '
                .'deleted.<br><a href="'.$plugins_url.'">Return to plugins screen</a>'
            );
        }
    }
}
