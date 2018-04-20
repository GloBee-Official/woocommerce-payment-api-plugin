<?php
/*
Plugin Name: WooCommerce GloBee Gateway
Plugin URI: https://globee.com/woocommerce
Description: Extends WooCommerce with a GloBee Payment gateway.
Version: 1.0
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

        }

        public function __destruct() {}
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_gateway_name_gateway($methods)
    {
        $methods[] = 'WC_Gateway_GloBee';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_globee_init' );
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
