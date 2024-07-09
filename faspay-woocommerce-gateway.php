<?php
/*
Plugin Name: Faspay Payment Gateway
Description: Faspay Payment Gateway Version: 4.0.0
Version: 4.0.0
Author: Faspay Development Team Author
Plugin URI: https://www.faspay.co.id
*/

include_once( plugin_dir_path( __FILE__ ) .'faspay-install.php' );
register_activation_hook( __FILE__ , 'faspay_activation_process' );
register_deactivation_hook( __FILE__ , 'faspay_uninstallation_process' );

add_action('plugins_loaded', 'woocommerce_faspay', 0);
function woocommerce_faspay(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 

    include_once(plugin_dir_path(__FILE__) . 'faspay-settings.php');
    include(plugin_dir_path(__FILE__) . 'class-gateway.php');
}


add_filter('woocommerce_payment_gateways', 'add_faspay_gateway');

function add_faspay_gateway($gateways) {
  $gateways[] = 'Faspay_Gateway';
  return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
function declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new Faspay_Gateway_Blocks );
        }
    );
}
?>