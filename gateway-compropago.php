<?php
/**
 * Gateway class
 **/

class woocommerce_compropago extends WC_Payment_Gateway {

	/**
	 * Test mode
	 */
	var $testmode;
	
	function __construct() { 
		global $woocommerce;
		
		$this->id				= 'compropago';
		$this->method_title 	= __('Compropago', 'woocommerce');
		$this->icon 			= apply_filters('woocommerce_compropago_icon', plugins_url('/images/compropago_z.png', __FILE__));
		$this->has_fields 		= false;
		
		// Load the form fields
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Get setting values
		$this->title 				= $this->settings['title'];
		$this->description 			= $this->settings['description'];
		$this->test_secret_key 		= $this->settings['test_secret_key'];
		$this->test_public_key 		= $this->settings['test_public_key'];
		$this->live_secret_key 		= $this->settings['live_secret_key'];
		$this->live_public_key 		= $this->settings['live_public_key'];
		// $this->send_customer 		= $this->settings['send_customer'];
		// $this->store_card 			= $this->settings['store_card'];
		
		$this->cvc 					= $this->settings['cvc'];
		$this->testmode 			= $this->settings['testmode'];
		$this->debug 				= $this->settings['debug'];
		
		// Logs
		if ($this->debug=='yes') $this->log = $woocommerce->logger();
		
		add_action('woocommerce_receipt_compropago', array(&$this, 'receipt_page'));
		add_action('admin_notices', array( &$this, 'ssl_check') );
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action('woocommerce_api_woocommerce_' . $this->id, array( $this, 'check_' . $this->id . '_resquest' ) );
		
		if ( !$this->is_valid_for_use() ) $this->enabled = false;
		
		//support subscriptions
		$this->supports = array( 'subscriptions', 'products', 'subscription_cancellation', 'subscription_reactivation');
		
		// When a subscriber or store manager cancel's a subscription in the store, suspend it with compropago
		add_action( 'cancelled_subscription_'.$this->id, array($this, 'cancel_subscriptions_for_order'), 10, 2 );
		// add_action( 'suspended_subscription_'.$this->id, array($this, 'suspend_subscription_for_order'), 10, 2 );
		add_action( 'reactivated_subscription_'.$this->id, array($this, 'reactivate_subscription_for_order'), 10, 2 );
	}
	
	/**
 	* Check if SSL is enabled and notify the user if SSL is not enabled
 	**/
	function ssl_check() {
		if (get_option('woocommerce_force_ssl_checkout')=='no' && $this->enabled=='yes') :
			echo '<div class="error"><p>'.sprintf(__('Compropago is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate - Compropago will only work in test mode.', 'woocommerce'), admin_url('admin.php?page=woocommerce')).'</p></div>';
		endif;
	}
	
	/**
     * Initialize Gateway Settings Form Fields
     */
    function init_form_fields() {
    
    	$this->form_fields = array(
    		'enabled' => array(
						'title' => __( 'Enable/Disable', 'woocommerce' ), 
						'label' => __( 'Enable Compropago', 'woocommerce' ), 
						'type' => 'checkbox', 
						'description' => '', 
						'default' => 'no'
					), 
					
			'title' => array(
						'title' => __( 'Title', 'woocommerce' ), 
						'type' => 'text', 
						'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ), 
						'default' => __( 'Efectivo', 'woocommerce' ),						
					), 
			
			'description' => array(
						'title' => __( 'Description', 'woocommerce' ), 
						'type' => 'textarea', 
						'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ), 
						'default' => "The quikest way to pay with Compropago",					
					),
			
			'webhook' => array(
						'title' => __( 'Notificaciones Automáticas', 'woocommerce' ), 
						'type' => 'text', 
						'description' => __( 'Si requiere notificaciones automáticas, agrege esta URL dentro de la sección Webhook del panel de control de compropago', 'woocommerce' ), 
						'default' => plugins_url( 'webhook.php' , __FILE__ ),					
					),
			
			'live_secret_key' => array(
						'title' => __( 'Live Secret Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Live Secret Key credentials from Compropago', 'woocommerce' ), 
						'default' => '',
						'css' => "width: 300px;"
					),
			'live_public_key' => array(
						'title' => __( 'Live Public Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Live Public Key credentials from Compropago', 'woocommerce' ), 
						'default' => '',
						'css' => "width: 300px;"
					),
 			'test_secret_key' => array(
						'title' => __( 'Test Secret Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Test Secret Key credentials from Compropago', 'woocommerce' ), 
						'default' => '',
						'css' => "width: 300px;"
					),
			'test_public_key' => array(
						'title' => __( 'Test Public Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Test Public Key credentials from Compropago', 'woocommerce' ), 
						'default' => '',
						'css' => "width: 300px;"
					),
					
			// 'send_customer' => array(
					// 'title' => __( 'Send Customer Data', 'woocommerce' ),
					// 'type' 	=> 'select',
					// 'description' => __( '<br />Sending customer data will create a customer in Compropago when an order is processed, based on the email address for the order. The credit card used will be attached to this customer, allowing you to charge them again in the future in Compropago.', 'woocommerce' ), 
					//'default' => 'choice',
					// 'options' => array(
					// 'never' => __('Never', 'woocommerce'),
					// 'choice' => __("Customer's choice", 'woocommerce'),
					// 'always' => __("Always", 'woocommerce'),
			// ),
		                 
			// 'store_card' => array(
					// 'title' => __( 'Allow Customers to Use Stored Cards', 'woocommerce' ),
					// 'type' => 'checkbox', 
						
					// 'label' => __( 'Allow Store Card Information', 'woocommerce' ), 
						
					// 'description' => '',
						
					// 'default' => 'no'
						
			// ),			
			'cvc' => array(
						'title' => __( 'CVC', 'woocommerce' ), 
						'label' => __( 'Con ComproPago puedes hacer tu pago en más de 130,000 puntos, entre tiendas restaurantes y farmacias.', 'woocommerce' ), 
						'type' => 'checkbox', 
						'description' => '', 
						'default' => 'yes'
					),
			
			'testmode' => array(
						'title' => __( 'Test Mode', 'woocommerce' ), 
						'label' => __( 'Enable Compropago Test', 'woocommerce' ), 
						'type' => 'checkbox', 
						'description' => __( 'Process transactions in Test Mode via the Compropago Test account.', 'woocommerce' ), 
						'default' => 'no'
					),
			'debug' => array(
						'title' => __( 'Debug', 'woocommerce' ), 
						'type' => 'checkbox', 
						'label' => __( 'Enable logging (<code>woocommerce/logs/compropago.txt</code>)', 'woocommerce' ), 
						'default' => 'no'
					)
			);
    }
    
    /**
	 * Admin Panel Options 
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 */
	function admin_options() {
    	?>
    	<h3><?php _e( 'Compropago Payment Gateway', 'woocommerce' ); ?></h3>
    	<p><?php _e( 'Con ComproPago puedes hacer tu pago en m&aacute;s de 130,000 puntos, entre tiendas restaurantes y farmacias.', 'woocommerce' ); ?></p>
    	<table class="form-table">
    		<?php
    		if ( $this->is_valid_for_use() ) :
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
    		else :
    			?>
            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Compropago does not support your store currency.', 'woocommerce' ); ?></p></div>
        		<?php
        		
    		endif;
    		?>
		</table><!--/.form-table-->
    	<?php
    }
	
	/**
	 * Get payment config
	 */
	function get_config($attr=''){
		$config = array(
			'livemode'=>'', 
			'apikey'=>'');
		
		if ($this->testmode=="no"){
			$config['livemode'] = true;
			$config['secret_key'] = $this->live_secret_key;
			$config['public_key'] = $this->live_public_key;
		} else {
			$config['livemode'] = false;
			$config['secret_key'] = $this->test_secret_key;
			$config['public_key'] = $this->test_public_key;
		}
		
		if(!empty($attr) && !empty($config[$attr])) {
			return $config[$attr];
		} 
		
		return $config;
	}
	
	/**
     * Check if this gateway is enabled and available in the user's country
     */
    function is_valid_for_use() {
    	/*
        if (!in_array(get_option('woocommerce_currency'), 
        	array('AED', 'AMD', 'ANG', 'ARS', 'AUD', 'AWG', 'AZN', 'BBD', 'BDT', 'BGN'
        		, 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BYR', 'BZD', 'CAD'
        		, 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP'
        		, 'DZD', 'EEK', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GIP'
        		, 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HTG', 'HUF', 'IDR', 'ILS'
        		, 'INR', 'ISK', 'JMD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KYD'
        		, 'KZT', 'LAK', 'LBP', 'LKR', 'LTL', 'LVL', 'MAD', 'MDL', 'MNT', 'MOP'
        		, 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK'
        		, 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR'
        		, 'RON', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL'
        		, 'SOS', 'STD', 'SVC', 'SZL', 'THB', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS'
        		, 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'WST', 'XAF', 'XCD', 'XOF'
        		, 'XPF', 'YER', 'ZAR', 'ZMK', 'ZWD'))) 
        	return false;
		 */ 
        return true;
    }

	/**
     * Payment form on checkout page
     */
	function payment_fields() {
?>
		<?php if ($this->testmode=='yes') : ?><p><?php _e('TEST MODE/SANDBOX ENABLED', 'woocommerce'); ?></p><?php endif; ?>
		<?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>
<?php

	}
	
 	/**
	 * Get args for passing
	 * 
	 **/
	function get_params( $order) {
		global $woocommerce;
		
		if ($this->debug=='yes') 
			$this->log->add( 'compropago', 'Generating payment form for order #' . $order->id);
		
		$token = $this->get_request('compropago_token');
		
		$params = array();
		
		//Order info------------------------------------		
		$params['amount'] 			= number_format($order->order_total, 2, '.', '') * 100;		
		$params['currency'] 		= get_option('woocommerce_currency');
		
		//Item name
		$item_names = array();
		if (sizeof($order->get_items())>0) : foreach ($order->get_items() as $item) :
			if ($item['qty']) $item_names[] = $item['name'] . ' x ' . $item['qty'];
		endforeach; endif;
		
		$params['description'] 		= sprintf( __('Order %s' , 'woocommerce'), $order->id ) . " - " . implode(', ', $item_names);
		
		$params['card'] 	= $token;
		
		return $params;
	}
	
	
	/**
     * Process the payment
	 * 
     */
	function process_payment($order_id) {
		global $woocommerce;
		
		$order = new WC_Order( $order_id );
		if ($this->debug=='yes') 
			$this->log->add( 'compropago', 'Redirect url: ' . add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))));
		// Return thank you redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
	}

	/**
	 * receipt_page
	 * 
	 **/
	function receipt_page( $order_id ) {
		global $woocommerce;
		$product_name = NULL;
		
		$order = new WC_Order( $order_id );
		$public_key = $this->get_config('public_key');
		$items = $order->get_items();
		if ( count( $items ) > 0 ) {
			echo '<p>'.__('Muchas gracia por tu compra, esperamos tu pago.', 'woocommerce').'</p>';
			foreach ( $items as $item ) {
				if ( is_null( $product_name ) ) {
					$product_name = $item['name'];
				} else {
					$product_name = $product_name . ' ' . $item['name'];
				}
				$product_id = $item['product_id'];
			}
			
			$payment_url = "https://www.compropago.com/comprobante/?public_key=".$public_key;
			$payment_url .= "&customer_data_blocked=true";
			$payment_url .= "&app_client_name=woocommerce_compropago";
			$payment_url .= "&app_client_version=".WOOCOMMERCE_VERSION;
			$payment_url .= "&customer_name=".$order->billing_first_name . " " . $order->billing_last_name;
			$payment_url .= "&customer_email=".$order->billing_email;
			$payment_url .= "&product_price=".$order->get_total();
			$payment_url .= "&product_id=".$order_id;
			$payment_url .= "&product_name=".$product_name;
			$payment_url .= "&success_url=".$this->get_return_url( $order );

			$woocommerce->enqueue_js("
			jQuery(document).ready(function($) {
				$.fancybox.open({
					modal: true,
					overlayShow: true,
					hideOnOverlayClick: false,
					hideOnContentClick: false,
					enableEscapeButton: false,
					showCloseButton: false,
					href : ".$payment_url.",
					type : 'iframe',
					padding : 5
				});
				$('#payment_btn').click(function(event) {
					event.preventDefault();
					$.fancybox.open({
						modal: true,
						overlayShow: true,
						hideOnOverlayClick: false,
						hideOnContentClick: false,
						enableEscapeButton: false,
						showCloseButton: false,
						href : ".$payment_url.",
						type : 'iframe',
						padding : 5
					});
				});
			});");
		}
	}

	/**
	* Check for Compropago notification
	* */
	function check_compropago_resquest() {
		global $woocommerce;
		
		$body = @file_get_contents('php://input');
		if ( !empty( $body ) ) {
			$event_json = json_decode($body);
			$order_id = $event_json->data->object->payment_details->{'product_id'};
			$order = new WC_Order((int) $order_id);
			// Check order not already completed
			if ($order->status == 'completed'){
				if ($this->debug=='yes') $this->log->add( 'servired', 'Aborting, Order #' . $posted['custom'] . ' is already complete.' );
				return;
			}
			$status = $event_json->{'type'};
			$order->add_order_note( __('Recibida notificación de compropago. Status:', 'wc_compropago').$status );
			$order->payment_complete();

			/* @TODO: Add some kind of verification */
			if ( $status == 'charge.pending' ) {
				compropago_status_function( $product_id, $status );
			}
			echo json_encode( $event_json );
		}
	}
	
	/**
	 * Get post data if set
	 **/
	private function get_request($name) {
		if(isset($_REQUEST[$name])) {
			return trim($_REQUEST[$name]);
		}
		return NULL;
	}
	
} // end woocommerce_compropago
