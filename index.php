<?php
/*
Plugin Name: WC Compropago Gateway
Plugin URI: http://modulosdepago.es/
Description: The Compropago payment gateway plugin for WooCommerce, Therefore an SSL certificate is required to ensure your customer credit card details are safe. Based on Rodrigo Ayala's <rodrigo@compropago.com> version.
Version: 2.0.20
Author: Mikel Martin <mikel@zhenit.com>
Author URI: http://ZhenIT.com/
*/

add_action('plugins_loaded', 'woocommerce_compropago_init', 0);

function woocommerce_compropago_init() {

	if ( ! class_exists( 'Woocommerce' ) ) { return; }
	
	/**
 	 * Localication
	 */
	load_plugin_textdomain( 'wc_compropago', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	if(!defined('COMPROPAGO_SDK')) {
		define('COMPROPAGO_SDK', 1);
		require_once('gateway-compropago.php');
	}
	
	require_once('includes/gateway-request.php');
	require_once('includes/gateway-response.php');
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function add_compropago_gateway($methods) {
		$methods[] = 'woocommerce_compropago';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'add_compropago_gateway' );
}

function compropago_status_function ( $order_id, $status = 'processing' ) { 
	$order = new WC_Order($order_id);
	$order->update_status( $status );
	return true;
}

function request_data_compropago() {
	$body = @file_get_contents('php://input'); 
	$event_json = json_decode($body);

    // Almacenando los valores del JSON 
	$id = $event_json->data->object->{'id'};
    $status = $event_json->{'type'};
	if ( $status == 'charge.pending' ) {
		$status = 'processing';
	} elseif ( $status == 'charge.success' ) {
		$status = 'completed';
	}
    $product_id = $event_json->data->object->payment_details->{'product_id'};
	compropago_status_function( $product_id, $status );
	
	echo $body;
}
add_action('admin_post_nopriv_compropago', 'request_data_compropago' );
add_action('admin_post_compropago', 'request_data_compropago' );
