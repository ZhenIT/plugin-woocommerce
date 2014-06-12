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
		$this->method_title 	= __('Compropago', 'wc_compropago');
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

		$this->form_submission_method = false; //Redirect
		// Logs
		if ($this->debug=='yes') $this->log = $woocommerce->logger();
		
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
			echo '<div class="error"><p>'.sprintf(__('Compropago is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate - Compropago will only work in test mode.', 'wc_compropago'), admin_url('admin.php?page=woocommerce')).'</p></div>';
		endif;
	}
	
	/**
     * Initialize Gateway Settings Form Fields
     */
    function init_form_fields() {
    
    	$this->form_fields = array(
    		'enabled' => array(
						'title' => __( 'Enable/Disable', 'wc_compropago' ),
						'label' => __( 'Enable Compropago', 'wc_compropago' ),
						'type' => 'checkbox', 
						'description' => '', 
						'default' => 'no'
					), 
					
			'title' => array(
						'title' => __( 'Title', 'wc_compropago' ),
						'type' => 'text', 
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc_compropago' ),
						'default' => __( 'Efectivo', 'wc_compropago' ),
					), 
			
			'description' => array(
						'title' => __( 'Description', 'wc_compropago' ),
						'type' => 'textarea', 
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc_compropago' ),
						'default' => "The quikest way to pay with Compropago",					
					),
			
			'webhook' => array(
						'title' => __( 'Notificaciones Automáticas', 'wc_compropago' ),
						'type' => 'text', 
						'description' => __( 'Si requiere notificaciones automáticas, agrege esta URL dentro de la sección Webhook del panel de control de compropago', 'wc_compropago' ),
						'default' => add_query_arg(array('wc-api'=>'woocommerce_'. $this->id), home_url( '/' ) ),
					),
			
			'live_secret_key' => array(
						'title' => __( 'Live Secret Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Live Secret Key credentials from Compropago', 'wc_compropago' ),
						'default' => '',
						'css' => "width: 300px;"
					),
			'live_public_key' => array(
						'title' => __( 'Live Public Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Live Public Key credentials from Compropago', 'wc_compropago' ),
						'default' => '',
						'css' => "width: 300px;"
					),
 			'test_secret_key' => array(
						'title' => __( 'Test Secret Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Test Secret Key credentials from Compropago', 'wc_compropago' ),
						'default' => '',
						'css' => "width: 300px;"
					),
			'test_public_key' => array(
						'title' => __( 'Test Public Key' ), 
						'type' => 'text', 
						'description' => __( 'Get your Test Public Key credentials from Compropago', 'wc_compropago' ),
						'default' => '',
						'css' => "width: 300px;"
					),
					
			// 'send_customer' => array(
					// 'title' => __( 'Send Customer Data', 'wc_compropago' ),
					// 'type' 	=> 'select',
					// 'description' => __( '<br />Sending customer data will create a customer in Compropago when an order is processed, based on the email address for the order. The credit card used will be attached to this customer, allowing you to charge them again in the future in Compropago.', 'wc_compropago' ),
					//'default' => 'choice',
					// 'options' => array(
					// 'never' => __('Never', 'wc_compropago'),
					// 'choice' => __("Customer's choice", 'wc_compropago'),
					// 'always' => __("Always", 'wc_compropago'),
			// ),
		                 
			// 'store_card' => array(
					// 'title' => __( 'Allow Customers to Use Stored Cards', 'wc_compropago' ),
					// 'type' => 'checkbox', 
						
					// 'label' => __( 'Allow Store Card Information', 'wc_compropago' ),
						
					// 'description' => '',
						
					// 'default' => 'no'
						
			// ),			
			'cvc' => array(
						'title' => __( 'CVC', 'wc_compropago' ),
						'label' => __( 'Con ComproPago puedes hacer tu pago en más de 130,000 puntos, entre tiendas restaurantes y farmacias.', 'wc_compropago' ),
						'type' => 'checkbox', 
						'description' => '', 
						'default' => 'yes'
					),
			
			'testmode' => array(
						'title' => __( 'Test Mode', 'wc_compropago' ),
						'label' => __( 'Enable Compropago Test', 'wc_compropago' ),
						'type' => 'checkbox', 
						'description' => __( 'Process transactions in Test Mode via the Compropago Test account.', 'wc_compropago' ),
						'default' => 'no'
					),
			'debug' => array(
						'title' => __( 'Debug', 'wc_compropago' ),
						'type' => 'checkbox', 
						'label' => __( 'Enable logging (<code>woocommerce/logs/compropago.txt</code>)', 'wc_compropago' ),
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
    	<h3><?php _e( 'Compropago Payment Gateway', 'wc_compropago' ); ?></h3>
    	<p><?php _e( 'Con ComproPago puedes hacer tu pago en m&aacute;s de 130,000 puntos, entre tiendas restaurantes y farmacias.', 'wc_compropago' ); ?></p>
    	<table class="form-table">
    		<?php
    		if ( $this->is_valid_for_use() ) :
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
    		else :
    			?>
            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'wc_compropago' ); ?></strong>: <?php _e( 'Compropago does not support your store currency.', 'wc_compropago' ); ?></p></div>
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
		<?php if ($this->testmode=='yes') : ?><p><?php _e('TEST MODE/SANDBOX ENABLED', 'wc_compropago'); ?></p><?php endif; ?>
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
		
		$params['description'] 		= sprintf( __('Order %s' , 'wc_compropago'), $order->id ) . " - " . implode(', ', $item_names);
		
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
		if ( ! $this->form_submission_method ) {
			$compropago_args = $this->get_compropago_args( $order );
			$compropago_addr = 'https://www.compropago.com/comprobante/?';
			return array(
				'result'    => 'success',
				'redirect'  => $compropago_addr . http_build_query( $compropago_args, '', '&' )
			);
		}
	}

	function get_compropago_args($order){
		global $woocommerce;
		$compropago_args = array(
			'customer_data_blocked'	=> 'true',
			'app_client_name'		=> 'woocommerce_compropago',
			'app_client_version'	=> WOOCOMMERCE_VERSION,
			'customer_name'			=> $order->billing_first_name . " " . $order->billing_last_name,
			'customer_email'		=> $order->billing_email,
			'product_price'			=> $order->get_total(),
			'product_id'			=> $order->id,
			'product_name'			=> $product_name,
			'success_url'			=> $this->get_return_url( $order ),
			'public_key'			=> $this->get_config('public_key')
		);
		$compropago_args = apply_filters( 'woocommerce_compropago_args', $compropago_args );
		return $compropago_args;
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
// 			if ( $status == 'charge.pending' ) {
// 				compropago_status_function( $order_id, $status );
// 			}
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
