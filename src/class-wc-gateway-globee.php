<?php
/*
Plugin Name: GloBee
Plugin URI: https://globee.com/woocommerce
Description: Accepts cryptocurrency payments on your WooCommerce Shop using GloBee.
Version: 1.1.0
Author: GloBee
Author URI: https://globee.com/

License:           Copyright 2018 GloBee., MIT License
License URI:       https://github.com/GloBee-Official/woocommerce-payment-api-plugin/blob/master/LICENSE
GitHub Plugin URI: https://github.com/GloBee-Official/woocommerce-payment-api-plugin/
*/

if (false === defined('ABSPATH')) {
    exit;
}

require_once __DIR__.'/autoload.php';

add_action('plugins_loaded', 'globee_woocommerce_init', 0);
register_activation_hook(__FILE__, 'globee_woocommerce_activate');

function globee_woocommerce_init()
{
    if (false === class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-gateway-globee', false, dirname(plugin_basename(__FILE__)).'/languages');

    /**
     * Add the Gateway to WooCommerce
     *
     * @param $methods
     * @return array
     */
    function woocommerce_add_globee_gateway($methods)
    {
        $methods[] = 'GloBee\\WooCommerce\\Gateway';

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
    update_option('globee_woocommerce_version', '1.1.0');

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
