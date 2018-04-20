<?php
/*
Plugin Name: GloBee
Plugin URI: https://globee.com/woocommerce
Description: Accepts cryptocurrency payments on your WooCommerce Shop using GloBee.
Version: 1.0.0
Author: GloBee
Author URI: https://globee.com/
*/

if (false === defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'woocommerce_globee_init', 0);

function woocommerce_globee_init()
{
    if (true === class_exists('WC_Gateway_Bitpay')) {
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
            $this->icon = plugin_dir_url(__FILE__).'assets/images/icon.png';
            $this->has_fields = false;
            $this->method_title = 'GloBee';
            $this->method_description = 'GloBee allows you to accept cryptocurrency payments on your WooCommerce store.';
            $this->order_button_text  = __('Proceed to GloBee', 'globee');

            $this->init_form_fields();
            $this->init_settings();

            $this->globee_net = $this->get_option('globee_net');
            $this->payment_api_key = $this->get_option('globee_payment_api_key');

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function __destruct()
        {

        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'globee' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable GloBee Payment Gateway', 'globee' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'globee' ),
                    'type' => 'text',
                    'description' => __( 'GloBee Cryptocurrency Payment Gateway', 'globee' ),
                    'default' => __( 'GloBee', 'globee' ),
                    'desc_tip' => false,
                ),
                'description' => array(
                    'title' => __('Customer Message', 'globee'),
                    'type' => 'textarea',
                    'description' => __('GloBee Cryptocurrency Payment Gateway', 'globee'),
                    'default' => 'You will be redirected to GloBee to complete your purchase.',
                    'desc_tip' => false,
                ),
                'token' => array(
                    'title' => __('Payment API Token', 'globee'),
                    'type' => 'api_token',
                    'description' => __('GloBee Cryptocurrency Payment Gateway', 'globee'),
                    'default' => 'Your Payment API Token',
                    'desc_tip' => false,
                ),
                'transaction_speed' => array(
                    'title' => __('Transaction Speed', 'globee'),
                    'type' => 'select',
                    'description' => __('Choose your preferred transaction speed. View your Settlement Settings page on GloBee for more details.', 'globee'),
                    'default' => 'medium',
                    'options' => array(
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ),
                    'desc_tip' => true,
                ),
                'notification_url' => array(
                    'title' => __('Notification URL', 'globee'),
                    'type' => 'url',
                    'description' => __('GloBee will send IPNs for orders to this URL with the GloBee payment request data', 'globee'),
                    'default' => '',
                    'placeholder' => WC()->api_request_url('WC_Gateway_GloBee'),
                    'desc_tip' => true,
                ),
                'redirect_url' => array(
                    'title' => __('Redirect URL', 'globee'),
                    'type' => 'url',
                    'description' => __('After paying the GloBee invoice, users will be redirected back to this URL', 'globee'),
                    'default' => '',
                    'placeholder' => $this->get_return_url(),
                    'desc_tip' => true,
                ),
                'cancel_url' => array(
                    'title' => __('Cancel URL', 'globee'),
                    'type' => 'url',
                    'description' => __('After paying the GloBee invoice, users will be redirected back to this URL', 'globee'),
                    'default' => '',
                    'placeholder' => $this->get_return_url(),
                    'desc_tip' => true,
                ),
                'support_details' => array(
                    'title' => __( 'Plugin & Support Information', 'globee' ),
                    'type' => 'title',
                    'description' => sprintf(
                        __('This plugin version is %s and your PHP version is %s. If you need assistance, please contact support@globee.com. Thank you for using GloBee!', 'globee'),
                        get_option('woocommerce_bitpay_version'),
                        PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
                    ),
                ),
            );
        }

        public function generate_payment_api_key_html()
        {
            ob_start();
            wp_enqueue_style('globee-key', plugins_url('assets/css/style.css', __FILE__));
            wp_enqueue_script('globee-pairing', plugins_url('assets/js/pairing.js', __FILE__), array('jquery'), null, true);
            wp_localize_script('globee-pairing', 'GloBeeAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'pairNonce' => wp_create_nonce('globee-pair-nonce'),
                'revokeNonce' => wp_create_nonce('globee-revoke-nonce')
            ));
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">GloBee Payment API</th>
                <td class="forminp" id="globee_api_key">
                    <div id="globee_api_key_form">
                        <div class="globee-token globee-token-%s">
                            <header class="globee-token-header">
                                <div class="globee-token-logo"><img src="<?= plugins_url('assets/images/logo.png', __FILE__); ?>"></div>
                                <button class="globee-token-revoke fa fa-ban"></button>
                            </header>
                            <div class="globee-token-prop"><span class="globee-token-label">Key:</span> <?= $this->payment_api_key; ?></div>
                        </div>
                    </div>
                    <script type="text/javascript">
                        var ajax_loader_url = '<?= plugins_url('assets/images/ajax-loader.gif', __FILE__); ?>';
                    </script>
                </td>
            </tr>
            <?php

            return ob_get_clean();
        }

        # REWRITE THE FOLLOWING ########################################################################################

        public function generate_order_states_html()
        {
            $this->log('    [Info] Entered generate_order_states_html()...');

            ob_start();

            $bp_statuses = array('new'=>'New Order', 'paid'=>'Paid', 'confirmed'=>'Confirmed', 'complete'=>'Complete', 'invalid'=>'Invalid');
            $df_statuses = array('new'=>'wc-on-hold', 'paid'=>'wc-processing', 'confirmed'=>'wc-processing', 'complete'=>'wc-completed', 'invalid'=>'wc-failed');

            $wc_statuses = wc_get_order_statuses();

            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">Order States:</th>
                <td class="forminp" id="bitpay_order_states">
                    <table cellspacing="0">
                        <?php

                        foreach ($bp_statuses as $bp_state => $bp_name) {
                            ?>
                            <tr>
                                <th><?php echo $bp_name; ?></th>
                                <td>
                                    <select name="woocommerce_bitpay_order_states[<?php echo $bp_state; ?>]">
                                        <?php

                                        $order_states = get_option('woocommerce_bitpay_settings');
                                        $order_states = $order_states['order_states'];
                                        foreach ($wc_statuses as $wc_state => $wc_name) {
                                            $current_option = $order_states[$bp_state];

                                            if (true === empty($current_option)) {
                                                $current_option = $df_statuses[$bp_state];
                                            }

                                            if ($current_option === $wc_state) {
                                                echo "<option value=\"$wc_state\" selected>$wc_name</option>\n";
                                            } else {
                                                echo "<option value=\"$wc_state\">$wc_name</option>\n";
                                            }
                                        }

                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <?php
                        }

                        ?>
                    </table>
                </td>
            </tr>
            <?php

            $this->log('    [Info] Leaving generate_order_states_html()...');

            return ob_get_clean();
        }

        public function save_order_states()
        {
            $this->log('    [Info] Entered save_order_states()...');

            $bp_statuses = array(
                'new'      => 'New Order',
                'paid'      => 'Paid',
                'confirmed' => 'Confirmed',
                'complete'  => 'Complete',
                'invalid'   => 'Invalid',
            );

            $wc_statuses = wc_get_order_statuses();

            if (true === isset($_POST['woocommerce_bitpay_order_states'])) {

                $bp_settings = get_option('woocommerce_bitpay_settings');
                $order_states = $bp_settings['order_states'];

                foreach ($bp_statuses as $bp_state => $bp_name) {
                    if (false === isset($_POST['woocommerce_bitpay_order_states'][ $bp_state ])) {
                        continue;
                    }

                    $wc_state = $_POST['woocommerce_bitpay_order_states'][ $bp_state ];

                    if (true === array_key_exists($wc_state, $wc_statuses)) {
                        $this->log('    [Info] Updating order state ' . $bp_state . ' to ' . $wc_state);
                        $order_states[$bp_state] = $wc_state;
                    }

                }
                $bp_settings['order_states'] = $order_states;
                update_option('woocommerce_bitpay_settings', $bp_settings);
            }

            $this->log('    [Info] Leaving save_order_states()...');
        }

        public function validate_order_states_field()
        {
            $order_states = $this->get_option('order_states');

            if ( isset( $_POST[ $this->plugin_id . $this->id . '_order_states' ] ) ) {
                $order_states = $_POST[ $this->plugin_id . $this->id . '_order_states' ];
            }
            return $order_states;
        }

        public function validate_url_field($key)
        {
            $url = $this->get_option($key);

            if ( isset( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ) {
                if (filter_var($_POST[ $this->plugin_id . $this->id . '_' . $key ], FILTER_VALIDATE_URL) !== false) {
                    $url = $_POST[ $this->plugin_id . $this->id . '_' . $key ];
                } else {
                    $url = '';
                }
            }
            return $url;
        }

        public function validate_redirect_url_field()
        {
            $redirect_url = $this->get_option('redirect_url', '');

            if ( isset( $_POST['woocommerce_bitpay_redirect_url'] ) ) {
                if (filter_var($_POST['woocommerce_bitpay_redirect_url'], FILTER_VALIDATE_URL) !== false) {
                    $redirect_url = $_POST['woocommerce_bitpay_redirect_url'];
                } else {
                    $redirect_url = '';
                }
            }
            return $redirect_url;
        }

        public function thankyou_page($order_id)
        {
            $this->log('    [Info] Entered thankyou_page with order_id =  ' . $order_id);

            // Intentionally blank.

            $this->log('    [Info] Leaving thankyou_page with order_id =  ' . $order_id);
        }

        public function process_payment($order_id)
        {
            $this->log('    [Info] Entered process_payment() with order_id = ' . $order_id . '...');

            if (true === empty($order_id)) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but the order_id was missing.');
                throw new \Exception('The GloBee payment plugin was called to process a payment but the order_id was missing. Cannot continue!');
            }

            $order = wc_get_order($order_id);

            if (false === $order) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not retrieve the order details for order_id ' . $order_id);
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not retrieve the order details for order_id ' . $order_id . '. Cannot continue!');
            }

            $notification_url = $this->get_option('notification_url', WC()->api_request_url('WC_Gateway_Bitpay'));
            $this->log('    [Info] Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $notification_url);

            // Mark new order according to user settings (we're awaiting the payment)
            $new_order_states = $this->get_option('order_states');
            $new_order_status = $new_order_states['new'];
            $order->update_status($new_order_status, 'Awaiting payment notification from GloBee.');

            $thanks_link = $this->get_return_url($order);

            $this->log('    [Info] The variable thanks_link = ' . $thanks_link . '...');

            // Redirect URL & Notification URL
            $redirect_url = $this->get_option('redirect_url', $thanks_link);
            $this->log('    [Info] The variable redirect_url = ' . $redirect_url  . '...');

            $this->log('    [Info] Notification URL is now set to: ' . $notification_url . '...');

            // Setup the currency
            $currency_code = get_woocommerce_currency();

            $this->log('    [Info] The variable currency_code = ' . $currency_code . '...');

            $currency = new \Bitpay\Currency($currency_code);

            if (false === isset($currency) && true === empty($currency)) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not instantiate a Currency object.');
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not instantiate a Currency object. Cannot continue!');
            }

            // Get a BitPay Client to prepare for invoice creation
            $client = new \Bitpay\Client\Client();

            if (false === isset($client) && true === empty($client)) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not instantiate a client object.');
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not instantiate a client object. Cannot continue!');
            }

            if ('livenet' === $this->api_network) {
                $client->setNetwork(new \Bitpay\Network\Livenet());
                $this->log('    [Info] Set network to Livenet...');
            } else {
                $client->setNetwork(new \Bitpay\Network\Testnet());
                $this->log('    [Info] Set network to Testnet...');
            }

            $curlAdapter = new \Bitpay\Client\Adapter\CurlAdapter();

            if (false === isset($curlAdapter) || true === empty($curlAdapter)) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not instantiate a CurlAdapter object.');
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not instantiate a CurlAdapter object. Cannot continue!');
            }

            $client->setAdapter($curlAdapter);

            if (false === empty($this->api_key)) {
                $client->setPrivateKey($this->api_key);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not set client->setPrivateKey to this->api_key. The empty() check failed!');
                throw new \Exception(' The GloBee payment plugin was called to process a payment but could not set client->setPrivateKey to this->api_key. The empty() check failed!');
            }

            if (false === empty($this->api_pub)) {
                $client->setPublicKey($this->api_pub);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not set client->setPublicKey to this->api_pub. The empty() check failed!');
                throw new \Exception(' The GloBee payment plugin was called to process a payment but could not set client->setPublicKey to this->api_pub. The empty() check failed!');
            }

            if (false === empty($this->api_key)) {
                $client->setToken($this->api_key);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not set client->setToken to this->api_key. The empty() check failed!');
                throw new \Exception(' The GloBee payment plugin was called to process a payment but could not set client->setToken to this->api_key. The empty() check failed!');
            }

            $this->log('    [Info] Key and key empty checks passed.  Parameters in client set accordingly...');

            // Setup the Invoice
            $invoice = new \Bitpay\Invoice();

            if (false === isset($invoice) || true === empty($invoice)) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not instantiate an Invoice object.');
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not instantiate an Invoice object. Cannot continue!');
            } else {
                $this->log('    [Info] Invoice object created successfully...');
            }

            $order_number = $order->get_order_number();
            $invoice->setOrderId((string)$order_number);
            $invoice->setCurrency($currency);
            $invoice->setFullNotifications(true);

            // Add a priced item to the invoice
            $item = new \Bitpay\Item();

            if (false === isset($item) || true === empty($item)) {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not instantiate an item object.');
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not instantiate an item object. Cannot continue!');
            } else {
                $this->log('    [Info] Item object created successfully...');
            }

            $order_total = $order->calculate_totals();
            if (true === isset($order_total) && false === empty($order_total)) {
                $item->setPrice($order_total);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not set item->setPrice to $order->calculate_totals(). The empty() check failed!');
                throw new \Exception('The GloBee payment plugin was called to process a payment but could not set item->setPrice to $order->calculate_totals(). The empty() check failed!');
            }

            $invoice->setItem($item);

            // Add the Redirect and Notification URLs
            $invoice->setRedirectUrl($redirect_url);
            $invoice->setNotificationUrl($notification_url);
            $invoice->setTransactionSpeed($this->transaction_speed);

            try {
                $this->log('    [Info] Attempting to generate invoice for ' . $order->get_order_number() . '...');

                $invoice = $client->createInvoice($invoice);

                if (false === isset($invoice) || true === empty($invoice)) {
                    $this->log('    [Error] The GloBee payment plugin was called to process a payment but could not instantiate an invoice object.');
                    throw new \Exception('The GloBee payment plugin was called to process a payment but could not instantiate an invoice object. Cannot continue!');
                } else {
                    $this->log('    [Info] Call to generate invoice was successful: ' . $client->getResponse()->getBody());
                }
            } catch (\Exception $e) {
                $this->log('    [Error] Error generating invoice for ' . $order->get_order_number() . ', "' . $e->getMessage() . '"');
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

            $this->log('    [Info] Leaving process_payment()...');

            // Redirect the customer to the BitPay invoice
            return array(
                'result'   => 'success',
                'redirect' => $invoice->getUrl(),
            );
        }

        public function ipn_callback()
        {
            $this->log('    [Info] Entered ipn_callback()...');

            // Retrieve the Invoice ID and Network URL from the supposed IPN data
            $post = file_get_contents("php://input");

            if (true === empty($post)) {
                $this->log('    [Error] No post data sent to IPN handler!');
                error_log('[Error] GloBee plugin received empty POST data for an IPN message.');

                wp_die('No post data');
            } else {
                $this->log('    [Info] The post data sent to IPN handler is present...');
            }

            $json = json_decode($post, true);

            if (true === empty($json)) {
                $this->log('    [Error] Invalid JSON payload sent to IPN handler: ' . $post);
                error_log('[Error] GloBee plugin received an invalid JSON payload sent to IPN handler: ' . $post);

                wp_die('Invalid JSON');
            } else {
                $this->log('    [Info] The post data was decoded into JSON...');
            }

            if (false === array_key_exists('id', $json)) {
                $this->log('    [Error] No invoice ID present in JSON payload: ' . var_export($json, true));
                error_log('[Error] GloBee plugin did not receive an invoice ID present in JSON payload: ' . var_export($json, true));

                wp_die('No Invoice ID');
            } else {
                $this->log('    [Info] Invoice ID present in JSON payload...');
            }

            if (false === array_key_exists('url', $json)) {
                $this->log('    [Error] No invoice URL present in JSON payload: ' . var_export($json, true));
                error_log('[Error] GloBee plugin did not receive an invoice URL present in JSON payload: ' . var_export($json, true));

                wp_die('No Invoice URL');
            } else {
                $this->log('    [Info] Invoice URL present in JSON payload...');
            }

            // Get a BitPay Client to prepare for invoice fetching
            $client = new \Bitpay\Client\Client();

            if (false === isset($client) && true === empty($client)) {
                $this->log('    [Error] The GloBee payment plugin was called to handle an IPN but could not instantiate a client object.');
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not instantiate a client object. Cannot continue!');
            } else {
                $this->log('    [Info] Created new Client object in IPN handler...');
            }

            if (false === strpos($json['url'], 'test')) {
                $network = new \Bitpay\Network\Livenet();
                $this->log('    [Info] Set network to Livenet.');
            } else {
                $network = new \Bitpay\Network\Testnet();
                $this->log('    [Info] Set network to Testnet.');
            }

            $this->log('    [Info] Checking IPN response is valid via ' . $network->getName() . '...');

            $client->setNetwork($network);

            $curlAdapter = new \Bitpay\Client\Adapter\CurlAdapter();

            if (false === isset($curlAdapter) && true === empty($curlAdapter)) {
                $this->log('    [Error] The GloBee payment plugin was called to handle an IPN but could not instantiate a CurlAdapter object.');
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not instantiate a CurlAdapter object. Cannot continue!');
            } else {
                $this->log('    [Info] Created new CurlAdapter object in IPN handler...');
            }

            // Setting the Adapter param to a new BitPay CurlAdapter object
            $client->setAdapter($curlAdapter);

            if (false === empty($this->api_key)) {
                $client->setPrivateKey($this->api_key);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to handle an IPN but could not set client->setPrivateKey to this->api_key. The empty() check failed!');
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not set client->setPrivateKey to this->api_key. The empty() check failed!');
            }

            if (false === empty($this->api_pub)) {
                $client->setPublicKey($this->api_pub);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to handle an IPN but could not set client->setPublicKey to this->api_pub. The empty() check failed!');
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not set client->setPublicKey to this->api_pub. The empty() check failed!');
            }

            if (false === empty($this->api_key)) {
                $client->setToken($this->api_key);
            } else {
                $this->log('    [Error] The GloBee payment plugin was called to handle an IPN but could not set client->setToken to this->api_key. The empty() check failed!');
                throw new \Exception('The GloBee payment plugin was called to handle an IPN but could not set client->setToken to this->api_key. The empty() check failed!');
            }

            $this->log('    [Info] Key and key empty checks passed.  Parameters in client set accordingly...');

            // Fetch the invoice from BitPay's server to update the order
            try {
                $invoice = $client->getInvoice($json['id']);

                if (true === isset($invoice) && false === empty($invoice)) {
                    $this->log('    [Info] The IPN check appears to be valid.');
                } else {
                    $this->log('    [Error] The IPN check did not pass!');
                    wp_die('Invalid IPN');
                }
            } catch (\Exception $e) {
                $error_string = 'IPN Check: Can\'t find invoice ' . $json['id'];
                $this->log("    [Error] $error_string");
                $this->log("    [Error] " . $e->getMessage());

                wp_die($e->getMessage());
            }

            $order_id = $invoice->getOrderId();

            if (false === isset($order_id) && true === empty($order_id)) {
                $this->log('    [Error] The GloBee payment plugin was called to process an IPN message but could not obtain the order ID from the invoice.');
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but could not obtain the order ID from the invoice. Cannot continue!');
            } else {
                $this->log('    [Info] Order ID is: ' . $order_id);
            }

            //this is for the basic and advanced woocommerce order numbering plugins
            //if we need to apply other filters, just add them in place of the this one
            $order_id = apply_filters('woocommerce_order_id_from_number', $order_id);

            $order = wc_get_order($order_id);

            if (false === $order || 'WC_Order' !== get_class($order)) {
                $this->log('    [Error] The GloBee payment plugin was called to process an IPN message but could not retrieve the order details for order_id: "' . $order_id . '". If you use an alternative order numbering system, please see class-wc-gateway-bitpay.php to apply a search filter.');
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but could not retrieve the order details for order_id ' . $order_id . '. Cannot continue!');
            } else {
                $this->log('    [Info] Order details retrieved successfully...');
            }

            $current_status = $order->get_status();

            if (false === isset($current_status) && true === empty($current_status)) {
                $this->log('    [Error] The GloBee payment plugin was called to process an IPN message but could not obtain the current status from the order.');
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but could not obtain the current status from the order. Cannot continue!');
            } else {
                $this->log('    [Info] The current order status for this order is ' . $current_status);
            }

            $order_states = $this->get_option('order_states');

            $new_order_status = $order_states['new'];
            $paid_status      = $order_states['paid'];
            $confirmed_status = $order_states['confirmed'];
            $complete_status  = $order_states['complete'];
            $invalid_status   = $order_states['invalid'];

            $checkStatus = $invoice->getStatus();

            if (false === isset($checkStatus) && true === empty($checkStatus)) {
                $this->log('    [Error] The GloBee payment plugin was called to process an IPN message but could not obtain the current status from the invoice.');
                throw new \Exception('The GloBee payment plugin was called to process an IPN message but could not obtain the current status from the invoice. Cannot continue!');
            } else {
                $this->log('    [Info] The current order status for this invoice is ' . $checkStatus);
            }

            // Based on the payment status parameter for this
            // IPN, we will update the current order status.
            switch ($checkStatus) {

                // The "paid" IPN message is received almost
                // immediately after the BitPay invoice is paid.
                case 'paid':

                    $this->log('    [Info] IPN response is a "paid" message.');

                    if ($current_status == $complete_status       ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed')
                    {
                        $error_string = 'Paid IPN, but order has status: '.$current_status;
                        $this->log("    [Warning] $error_string");

                    } else {
                        $this->log('    [Info] This order has not been updated yet so setting new status...');

                        $order->update_status($paid_status);
                        $order->add_order_note(__('GloBee invoice paid. Awaiting network confirmation and payment completed status.', 'bitpay'));
                    }

                    break;

                // The "confirmed" status is sent when the payment is
                // confirmed based on your transaction speed setting.
                case 'confirmed':

                    $this->log('    [Info] IPN response is a "confirmed" message.');

                    if ($current_status == $complete_status       ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed')
                    {
                        $error_string = 'Confirmed IPN, but order has status: '.$current_status;
                        $this->log("    [Warning] $error_string");

                    } else {
                        $this->log('    [Info] This order has not been updated yet so setting confirmed status...');

                        $order->update_status($confirmed_status);
                        $order->add_order_note(__('GloBee invoice confirmed. Awaiting payment completed status.', 'bitpay'));
                    }

                    break;

                // The complete status is when the Bitcoin network
                // obtains 6 confirmations for this transaction.
                case 'complete':

                    $this->log('    [Info] IPN response is a "complete" message.');

                    if ($current_status == $complete_status       ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed')
                    {
                        $error_string = 'Complete IPN, but order has status: '.$current_status;
                        $this->log("    [Warning] $error_string");

                    } else {
                        $this->log('    [Info] This order has not been updated yet so setting complete status...');

                        $order->payment_complete();
                        $order->update_status($complete_status);
                        $order->add_order_note(__('GloBee invoice payment completed. Payment credited to your merchant account.', 'bitpay'));
                    }

                    break;

                // This order is invalid for some reason.
                // Either it's a double spend or some other
                // problem occurred.
                case 'invalid':

                    $this->log('    [Info] IPN response is a "invalid" message.');

                    if ($current_status == $complete_status       ||
                        'wc_'.$current_status == $complete_status ||
                        $current_status == 'completed')
                    {
                        $error_string = 'Paid IPN, but order has status: ' . $current_status;
                        $this->log("    [Warning] $error_string");

                    } else {
                        $this->log('    [Info] This order has a problem so setting "invalid" status...');

                        $order->update_status($invalid_status, __('Bitcoin payment is invalid for this order! The payment was not confirmed by the network within 1 hour. Do not ship the product for this order!', 'bitpay'));
                    }

                    break;

                // There was an unknown message received.
                default:

                    $this->log('    [Info] IPN response is an unknown message type. See error message below:');

                    $error_string = 'Unhandled invoice status: ' . $invoice->getStatus();
                    $this->log("    [Warning] $error_string");
            }

            $this->log('    [Info] Leaving ipn_callback()...');
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
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_globee_gateway' );
}

function woocommerce_globee_check_for_valid_system_requirements()
{
    global $wp_version;
    global $woocommerce;

    $errors = array();
    $contactYourWebAdmin = " in order to function. Please contact your web server administrator for assistance.";

    # PHP
    if (true === version_compare(PHP_VERSION, '5.4.0', '<')) {
        $errors[] = 'Your PHP version is too old. The GloBee payment plugin requires PHP 5.4 or higher' . $contactYourWebAdmin;
    }

    # Wordpress
    if (true === version_compare($wp_version, '4.9', '<')) {
        $errors[] = 'Your WordPress version is too old. The GloBee payment plugin requires Wordpress 4.9 or higher' . $contactYourWebAdmin;
    }

    # WooCommerce
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated' . $contactYourWebAdmin;

    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is ' . $woocommerce->version . '. The GloBee payment plugin requires WooCommerce 2.2 or higher'. $contactYourWebAdmin;
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

function woocommerce_globee_activate()
{
    $plugins_url = admin_url('plugins.php');

    $errorMessages = woocommerce_globee_check_for_valid_system_requirements();

    # Activate the plugin if there is no error messages
    if (! empty($errorMessages)) {
        wp_die($failed . '<br><a href="'.$plugins_url.'">Return to plugins screen</a>');
    }

    # Check if an older version of the plugin needs to be deactivated
    foreach (get_plugins() as $file => $plugin) {
        if ('GloBee Woocommerce' === $plugin['Name'] && true === is_plugin_active($file)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('GloBee for WooCommerce requires that the old plugin, <b>GloBee Woocommerce</b> be deactivated and deleted.<br><a href="'.$plugins_url.'">Return to plugins screen</a>');
        }
    }

    # Update the version number
    update_option('woocommerce_globee_version', '1.0.0');
}
