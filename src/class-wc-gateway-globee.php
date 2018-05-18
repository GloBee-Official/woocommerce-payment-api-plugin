<?php
/*
Plugin Name: GloBee
Plugin URI: https://globee.com/woocommerce
Description: Accepts cryptocurrency payments on your WooCommerce Shop using GloBee.
Version: 1.0.0
Author: GloBee
Author URI: https://globee.com/

License:           Copyright 2016-2017 GloBee., MIT License
License URI:       https://github.com/globee/woocommerce-payment-api-plugin/blob/master/LICENSE
GitHub Plugin URI: https://github.com/globee/woocommerce-payment-api-plugin
*/

if (false === defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'globee_woocommerce_init', 0);
register_activation_hook(__FILE__, 'globee_woocommerce_activate');

function globee_woocommerce_init()
{
    if (true === class_exists('WC_Gateway_globee')) {
        return;
    }

    if (false === class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-gateway-globee', false, dirname(plugin_basename(__FILE__)) . '/languages');

    /**
     * Gateway class
     */
    class WC_Gateway_GloBee extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'globee';
            $this->title = 'GloBee';
            $this->icon = plugin_dir_url(__FILE__).'assets/images/icon.png';
            $this->has_fields = false;
            $this->method_title = 'GloBee';
            $this->method_description = 'GloBee allows you to accept cryptocurrency payments on your WooCommerce store';
            $this->order_button_text  = __('Proceed to GloBee', 'globee');

            $this->init_form_fields();
            $this->init_settings();

            $this->network = $this->get_option('globee_network');
            $this->payment_api_key = $this->get_option('globee_payment_api_key');

            // Save settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

            // Save Order States
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'save_order_states']);

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
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => __('GloBee.com', 'globee'),
                    'type' => 'text',
                    'description' => __('The name users will see on the checkout page', 'globee'),
                    'default' => __( 'GloBee.com', 'globee'),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Customer Message', 'globee'),
                    'type' => 'textarea',
                    'description' => __('The message explaining to the user what will happen, after selecting GloBee as'
                        . ' their payment option', 'globee'),
                    'default' => 'You will be redirected to GloBee.com to complete your purchase.',
                    'desc_tip' => true,
                ],
                'payment_api_key' => [
                    'title' => __('Payment API Key', 'globee'),
                    'type' => 'text',
                    'description' => __('Your Payment API Key. You can find this key on the Payment API page of your '
                        . 'account on globee.com', 'globee'),
                    'default' => $this->payment_api_key,
                    'desc_tip' => true,
                ],
                'network' => [
                    'title' => __('Network', 'globee'),
                    'type' => 'select',
                    'description' => __('Choose if you want to use the GloBee Livenet or Testnet. Testnet is for '
                        . 'testing purposes and require a api key from the test system.', 'globee'),
                    'default' => 'livenet',
                    'options' => [
                        'livenet' => 'Livenet',
                        'testnet' => 'Testnet',
                    ],
                    'desc_tip' => true,
                ],
                'transaction_speed' => [
                    'title' => __('Transaction Speed', 'globee'),
                    'type' => 'select',
                    'description' => __('Choose your preferred transaction speed. View your Settlement Settings page '
                        . 'on GloBee for more details.', 'globee'),
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
                    'description' => __('GloBee will send IPNs for orders to this URL with the GloBee payment request '
                        . 'data', 'globee'),
                    'default' => WC()->api_request_url('WC_Gateway_GloBee'),
                    'placeholder' => WC()->api_request_url('WC_Gateway_GloBee'),
                    'desc_tip' => true,
                ],
                'redirect_url' => [
                    'title' => __('Redirect URL', 'globee'),
                    'type' => 'url',
                    'description' => __('After paying the GloBee invoice, users will be redirected back to this URL',
                        'globee'),
                    'default' => $this->get_return_url(),
                    'placeholder' => $this->get_return_url(),
                    'desc_tip' => true,
                ],
                'support_details' => [
                    'title' => __('Support & Version Information', 'globee'),
                    'type' => 'title',
                    'description' => sprintf(
                        __('<b>Versions</b><br/>Plugin version: %s<br/>PHP version: %s<br/><br/><b>Support</b><br/>If '
                            . 'you need assistance, please contact support@globee.com with your version numbers. Thank '
                            . 'you for using GloBee!', 'globee'),
                        get_option('globee_woocommerce_version', '1.0.0'),
                        PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
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
                'invalid' => 'Invalid'
            ];

            $statuses = [
                'new' => 'wc-on-hold',
                'paid' => 'wc-processing',
                'confirmed' => 'wc-processing',
                'complete' => 'wc-completed',
                'invalid' => 'wc-failed'
            ];

            $wcStatuses = wc_get_order_statuses();

            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">Order States:</th>
                <td class="forminp" id="globee_order_states">
                    <table cellspacing="0">
                        <?php foreach ($globeeStatuses as $globeeState => $globeeName) { ?>
                            <tr>
                                <th><?php echo $globeeName; ?></th>
                                <td>
                                    <select name="globee_woocommerce_order_states[<?php echo $globeeState; ?>]">
                                        <?php
                                        $orderStates = get_option('globee_woocommerce_settings')['order_states'];
                                        foreach ($wcStatuses as $wcState => $wcName) {
                                            $currentOption = $orderStates[$globeeState];
                                            if (true === empty($currentOption)) {
                                                $currentOption = $statuses[$globeeState];
                                            }
                                            echo "<option value='$wcState'";
                                            if ($currentOption === $wcState) {
                                                echo "selected";
                                            }
                                            echo ">$wcName</option>\n";
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
                $settings = get_option('globee_woocommerce_settings');
                $orderStates = $settings['order_states'];
                foreach ($globeeStatuses as $globeeState => $globeeName) {
                    if (false === isset($_POST['globee_woocommerce_order_states'][$globeeState])) {
                        continue;
                    }
                    $wcState = $_POST['globee_woocommerce_order_states'][$globeeState];
                    if (true === array_key_exists($wcState, $wcStatuses)) {
                        $orderStates[$globeeState] = $wcState;
                    }
                }
                $settings['order_states'] = $orderStates;
                update_option('globee_woocommerce_settings', $settings);
            }
        }

        public function validate_order_states_field()
        {
            $orderStates = $this->get_option('order_states');
            if (isset($_POST[$this->id.$this->plugin_id.'_order_states'])) {
                $orderStates = $_POST[$this->id.$this->plugin_id.'_order_states'];
            }
            return $orderStates;
        }

        public function validate_url_field($key)
        {
            $url = $this->get_option($key);
            if (isset($_POST[$this->id.$this->plugin_id.'_'.$key])) {
                if (filter_var($_POST[$this->id.$this->plugin_id.'_'.$key],FILTER_VALIDATE_URL) !== false) {
                    return $_POST[$this->id.$this->plugin_id.'_'.$key];
                }
                return '';
            }
            return $url;
        }

        public function validate_redirect_url_field()
        {
            $redirectUrl = $this->get_option('redirect_url', '');
            if ( isset($_POST['globee_woocommerce_redirect_url'])) {
                if (filter_var($_POST['globee_woocommerce_redirect_url'],FILTER_VALIDATE_URL) !== false) {
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

        # REWRITE THE FOLLOWING ########################################################################################

        public function process_payment($orderId)
        {
            if (true === empty($orderId)) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but the '
                    . 'orderId was missing. Cannot continue!');
            }

            $order = wc_get_order($orderId);

            if (false === $order) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could '
                    . 'not retrieve the order details for order_id ' . $orderId . '. Cannot continue!');
            }

            // Mark new order according to user settings (we're awaiting the payment)
            $newOrderStatus = $this->get_option('order_states')['new'];
            $order->update_status($newOrderStatus, 'Awaiting payment notification from GloBee.');

            // Redirect URL & Notification URL
            $redirectUrl = $this->get_option('redirect_url', $this->get_return_url($order));

            $connector = new \GloBee\PaymentApi\Connectors\GloBeeCurlConnector($this->payment_api_key);
            $paymentApi = new \GloBee\PaymentApi\PaymentApi($connector);
            $paymentRequest = new \GloBee\PaymentApi\Models\PaymentRequest();
            $paymentRequest->setSuccessUrl($this->get_option('redirect_url', $this->get_return_url()));
            $paymentRequest->setIpnUrl($this->get_option('notification_url', WC()->api_request_url('WC_Gateway_globee')));
            $paymentRequest->setCurrency(get_woocommerce_currency());
            $paymentRequest->setConfirmationSpeed($this->get_option('transaction_speed', 'medium'));
            $paymentRequest->setTotal($order->calculate_totals());
            $paymentRequest->setCustomerEmail('example@email.com');

            $response = $paymentApi->createPaymentRequest($paymentRequest);

            $paymentRequestId = $response->getId(); // Save this ID to know when payment has been made
            $redirectUrl = $response->getRedirectUrl(); // Redirect your client to this URL to make payment






            // Setup the currency
            $currency = new \globee\Currency();
            if (false === isset($currency) && true === empty($currency)) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not '
                    . 'instantiate a Currency object. Cannot continue!');
            }

            // Get a globee Client to prepare for invoice creation
            $client = new \globee\Client\Client();

            if (false === isset($client) && true === empty($client)) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not '
                    . 'instantiate a client object. Cannot continue!');
            }

            if ('livenet' === $this->api_network) {
                $client->setNetwork(new \globee\Network\Livenet());
            } else {
                $client->setNetwork(new \globee\Network\Testnet());
            }

            $curlAdapter = new \globee\Client\Adapter\CurlAdapter();

            if (false === isset($curlAdapter) || true === empty($curlAdapter)) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not '
                    . 'instantiate a CurlAdapter object. Cannot continue!');
            }

            $client->setAdapter($curlAdapter);


            // Setup the Invoice
            $invoice = new \globee\Invoice();

            if (false === isset($invoice) || true === empty($invoice)) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not '
                    . 'instantiate an Invoice object. Cannot continue!');
            }

            $order_number = $order->get_order_number();
            $invoice->setOrderId((string)$order_number);
            $invoice->setCurrency($currency);
            $invoice->setFullNotifications(true);

            // Add a priced item to the invoice
            $item = new \globee\Item();

            if (false === isset($item) || true === empty($item)) {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not '
                    . 'instantiate an item object. Cannot continue!');
            }

            $order_total = $order->calculate_totals();
            if (true === isset($order_total) && false === empty($order_total)) {
                $item->setPrice($order_total);
            } else {
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not '
                    . 'set item->setPrice to $order->calculate_totals(). The empty() check failed!');
            }

            $invoice->setItem($item);

            // Add the Redirect and Notification URLs
            $invoice->setRedirectUrl($redirectUrl);
            $invoice->setNotificationUrl($notificationUrl);
            $invoice->setTransactionSpeed($this->transaction_speed);

            try {
                $invoice = $client->createInvoice($invoice);
                if (false === isset($invoice) || true === empty($invoice)) {
                    throw new \Exception('The GloBee payment plugin was called to process a payment but could '
                        . 'not instantiate an invoice object. Cannot continue!');
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return array(
                    'result'    => 'success',
                    'messages'  => 'Sorry, but Bitcoin checkout with GloBee does not appear to be working.'
               );
            }

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Redirect the customer to the globee invoice
            return [
                'result'   => 'success',
                'redirect' => $invoice->getUrl(),
            ];
        }

        public function ipn_callback()
        {
            // Retrieve the Invoice ID and Network URL from the supposed IPN data
            $post = file_get_contents("php://input");

            if (true === empty($post)) {
                error_log('[Error] GloBee plugin received empty POST data for an IPN message.');
                wp_die('No post data');
            }

            $json = json_decode($post, true);

            if (true === empty($json)) {
                error_log('[Error] GloBee plugin received an invalid JSON payload sent to IPN handler: '
                    . $post);
                wp_die('Invalid JSON');
            }

            if (false === array_key_exists('id', $json)) {
                error_log('[Error] GloBee plugin did not receive an invoice ID present in JSON payload: '
                    . var_export($json, true));
                wp_die('No Invoice ID');
            }

            if (false === array_key_exists('url', $json)) {
                error_log('[Error] GloBee plugin did not receive an invoice URL present in JSON payload: '
                    . var_export($json, true));
                wp_die('No Invoice URL');
            }

            // Get a globee Client to prepare for invoice fetching
            $client = new \globee\Client\Client();

            if (false === isset($client) && true === empty($client)) {
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not '
                    . 'instantiate a client object. Cannot continue!');
            }

            if (false === strpos($json['url'], 'test')) {
                $network = new \globee\Network\Livenet();
            } else {
                $network = new \globee\Network\Testnet();
            }

            $client->setNetwork($network);

            $curlAdapter = new \globee\Client\Adapter\CurlAdapter();

            if (false === isset($curlAdapter) && true === empty($curlAdapter)) {
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not '
                    . 'instantiate a CurlAdapter object. Cannot continue!');
            }

            // Setting the Adapter param to a new globee CurlAdapter object
            $client->setAdapter($curlAdapter);

            if (false === empty($this->api_key)) {
                $client->setPrivateKey($this->api_key);
            } else {
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not '
                    . 'set client->setPrivateKey to this->api_key. The empty() check failed!');
            }

            if (false === empty($this->api_pub)) {
                $client->setPublicKey($this->api_pub);
            } else {
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not '
                    . 'set client->setPublicKey to this->api_pub. The empty() check failed!');
            }

            if (false === empty($this->api_key)) {
                $client->setToken($this->api_key);
            } else {
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not '
                    . 'set client->setToken to this->api_key. The empty() check failed!');
            }

            // Fetch the invoice from globee's server to update the order
            try {
                $invoice = $client->getInvoice($json['id']);
                if (!(true === isset($invoice) && false === empty($invoice))) {
                    wp_die('Invalid IPN');
                }
            } catch (\Exception $e) {
                wp_die($e->getMessage());
            }

            $orderId = $invoice->getOrderId();

            if (false === isset($orderId) && true === empty($orderId)) {
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but '
                    . 'could not obtain the order ID from the invoice. Cannot continue!');
            }

            //this is for the basic and advanced woocommerce order numbering plugins
            //if we need to apply other filters, just add them in place of the this one
            $orderId = apply_filters('woocommerce_order_id_from_number', $orderId);

            $order = wc_get_order($orderId);

            if (false === $order || 'WC_Order' !== get_class($order)) {
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but '
                    . 'could not retrieve the order details for order_id ' . $orderId . '. Cannot continue!');
            }

            $current_status = $order->get_status();

            if (false === isset($current_status) && true === empty($current_status)) {
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but '
                    . 'could not obtain the current status from the order. Cannot continue!');
            }

            $orderStates = $this->get_option('order_states');

            $newOrderStatus = $orderStates['new'];
            $paid_status = $orderStates['paid'];
            $confirmed_status = $orderStates['confirmed'];
            $complete_status = $orderStates['complete'];
            $invalid_status = $orderStates['invalid'];

            $checkStatus = $invoice->getStatus();

            if (false === isset($checkStatus) && true === empty($checkStatus)) {
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but '
                    . 'could not obtain the current status from the invoice. Cannot continue!');
            }

            switch ($checkStatus) {
                case 'paid':
                    if (!(
                        $current_status == $complete_status ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed'
                   )) {
                        $order->update_status($paid_status);
                        $order->add_order_note(__('GloBee invoice paid. Awaiting network confirmation and payment '
                            . 'completed status.', 'globee'));
                    }

                    break;

                case 'confirmed':
                    if (!(
                        $current_status == $complete_status ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed'
                   )) {
                        $order->update_status($confirmed_status);
                        $order->add_order_note(__('GloBee invoice confirmed. Awaiting payment completed status.',
                            'globee'));
                    }

                    break;

                case 'complete':
                    if (!(
                        $current_status == $complete_status ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed'
                   )) {
                        $order->payment_complete();
                        $order->update_status($complete_status);
                        $order->add_order_note(__('GloBee invoice payment completed. Payment credited to your merchant '
                            . 'account.', 'globee'));
                    }
                    break;

                case 'invalid':
                    if (!(
                        $current_status == $complete_status ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed'
                   )) {
                        $order->update_status($invalid_status, __('Bitcoin payment is invalid for this order! The '
                            . 'payment was not confirmed by the network within 1 hour. Do not ship the product for '
                            . 'this order!', 'globee'));
                    }
                    break;
            }
        }

        # END OF REWRITE ###############################################################################################
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
            $settingsLink = '<a href="' . get_bloginfo('wpurl')
                . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=globee">Settings</a>';
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
            . $contactYourWebAdmin;
    }

    # Wordpress
    if (true === version_compare($wp_version, '4.9', '<')) {
        $errors[] = 'Your WordPress version is too old. The GloBee payment plugin requires Wordpress 4.9 or higher'
            . $contactYourWebAdmin;
    }

    # WooCommerce
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated' . $contactYourWebAdmin;

    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is ' . $woocommerce->version . '. The GloBee payment plugin requires '
            . 'WooCommerce 2.2 or higher'. $contactYourWebAdmin;
    }

    # OpenSSL
    if (extension_loaded('openssl')  === false){
        $errors[] = 'The GloBee payment plugin requires the OpenSSL extension for PHP' . $contactYourWebAdmin;
    }

    # GMP
    if (false === extension_loaded('gmp')) {
        $errors[] = 'The GloBee payment plugin requires the GMP extension for PHP' . $contactYourWebAdmin;
    }

    # BCMath
    if (false === extension_loaded('bcmath')) {
        $errors[] = 'The GloBee payment plugin requires the BC Math extension for PHP' . $contactYourWebAdmin;
    }

    # Curl required
    if (false === extension_loaded('curl')) {
        $errors[] = 'The GloBee payment plugin requires the Curl extension for PHP' . $contactYourWebAdmin;
    }

    if (! empty($errors)) {
        return implode("<br>\n", $errors);
    }

    return null;
}

function globee_woocommerce_activate()
{
    $plugins_url = admin_url('plugins.php');

    $errorMessages = globee_woocommerce_check_for_valid_system_requirements();

    # Activate the plugin if there is no error messages
    if (! empty($errorMessages)) {
        wp_die($failed . '<br><a href="'.$plugins_url.'">Return to plugins screen</a>');
    }

    # Update the version number
    update_option('globee_woocommerce_version', '1.0.0');

    # Check if an older version of the plugin needs to be deactivated
    foreach (get_plugins() as $file => $plugin) {
        if ('GloBee Woocommerce' === $plugin['Name'] && true === is_plugin_active($file)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('GloBee for WooCommerce requires that the old plugin, <b>GloBee Woocommerce</b> be deactivated and '
                . 'deleted.<br><a href="'.$plugins_url.'">Return to plugins screen</a>');
        }
    }
}
