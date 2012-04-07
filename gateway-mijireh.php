<?php
/*
Plugin Name: WooCommerce Mijireh Checkout
Plugin URI: http://www.mijireh.com/integrations
Description: Extends WooCommerce with Mijireh Checkout integration
Version: 1.0
Author: Mijireh
Author URI: http://www.mijireh.com/

	Copyright: © 2012 Mijireh.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_mijireh_checkout_init', 0);

function woocommerce_mijireh_checkout_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Localisation
	 */
	load_plugin_textdomain('wc-mijireh-checkout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
	/**
 	 * Gateway class
 	 */
	class WC_Mijireh_Checkout extends WC_Payment_Gateway {
	  
	  public function __construct() {
	    global $woocommerce;
	    
	    if(!class_exists('Mijireh')) {
	      require 'mijireh.php';
	    }

  		$this->id = 'mijireh_checkout';
  		$this->icon = apply_filters('mijireh_checkout_icon', WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/mijireh.png');
      $this->has_fields = false;
      $this->liveurl = 'https://secure.mijireh.com/api/1/';
      $this->method_title = __( 'Mijireh Checkout', 'mijireh_checkout' );
      
      $this->init_form_fields();
      $this->init_settings();
      
      $this->title = $this->settings['title'];
  		$this->description = $this->settings['description'];
  		$this->access_key = $this->settings['access_key'];
  		
			add_action('init', array(&$this, 'mijireh_notification'));
  		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
  		
	  }
	  
	  public function mijireh_notification() {
	    if(isset($_GET['task']) && $_GET['task'] == 'mijireh_notification') {
	      global $woocommerce;
	      $woocommerce->cart->empty_cart();
	      unset($_SESSION['order_awaiting_payment']);
	      
	      try {
	        Mijireh::$access_key = $this->access_key;
  	      $mj_order = new Mijireh_Order($_GET['order_number']);
  	      $wc_order_id = $mj_order->get_meta_value('wc_order_id');
  	      $wc_order = new WC_Order($wc_order_id);
  	      $wc_order->payment_complete();
  	      $receipt_link = add_query_arg('key', $wc_order->order_key, add_query_arg('order', $wc_order_id, get_permalink(woocommerce_get_page_id('thanks'))));
  	      wp_redirect($receipt_link);
  	      exit();
	      }
	      catch (Mijireh_Exception $e) {
	        $woocommerce->add_error(__('Mijireh error:', 'mijireh_checkout') . $e->getMessage());
	      }
	      
	    }
	  }
  	
  	public function admin_options() {
  	  ?>
  	  <h3><?php _e('Mijireh Checkout', 'mijireh_checkout');?></h3>
  	  <p><a href="http://www.mijireh.com">Mijireh Checkout</a> 
  	    <?php _e('provides a fully PCI Compliant, secure way to collect and transmit credit card data to your payment gateway while keeping you in control of the design of your site.', 'mijireh_checkout'); ?></p>
  	  <table class="form-table">
  	    <?php $this->generate_settings_html(); ?>
		  </table><!--/.form-table-->
  	  <?php
  	}
    
    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
  				'title' => __( 'Enable/Disable', 'mijireh_checkout' ), 
					'type' => 'checkbox', 
					'label' => __( 'Enable Mijireh Checkout', 'mijireh_checkout' ), 
					'default' => 'yes'
				),
        'access_key' => array(
          'title' => __('Access Key', 'mijireh_checkout'),
          'type' => 'text',
          'description' => __('The Mijireh access key for your store', 'mijireh_checkout'),
          'default' => ''
        ),
        'title' => array(
  				'title' => __( 'Title', 'mijireh_checkout' ), 
					'type' => 'text', 
					'description' => __( 'This controls the title which the user sees during checkout.', 'mijireh_checkout' ), 
					'default' => __( 'Mijireh Checkout', 'mijireh_checkout' )
				)
      );
    }
    
    /**
     * There are no payment fields for Mijireh Checkout, but show the description if available.
     */
    public function payment_fields() {
    	if ($this->description) echo wpautop(wptexturize($this->description));
    }
    
    public function process_payment($order_id) {
      global $woocommerce;
      
      Mijireh::$access_key = $this->access_key;
      $mj_order = new Mijireh_Order();
      $wc_order = new WC_Order($order_id);
      
      // add items to order
      $items = $wc_order->get_items();
      foreach($items as $item) {
        $product = $wc_order->get_product_from_item($item);
        $mj_order->add_item($item['name'], $item['line_total'], $item['qty'], $product->sku);
      }
      
      // add billing address to order
      $billing = new Mijireh_Address();
      $billing->street = $wc_order->billing_address_1;
      $billing->apt_suite = $wc_order->billing_address_2;
      $billing->city = $wc_order->billing_city;
      $billing->state_province = $wc_order->billing_state;
      $billing->zip_code = $wc_order->billing_postcode;
      $billing->country = $wc_order->billing_country;
      $billing->company = $wc_order->billing_company;
      $billing->phone = $wc_order->billing_phone;
      if($billing->validate()) {
        $mj_order->set_billing_address($billing);
      }
      
      // add shipping address to order
      $shipping = new Mijireh_Address();
      $shipping->street = $wc_order->shipping_address_1;
      $shipping->apt_suite = $wc_order->shipping_address_2;
      $shipping->city = $wc_order->shipping_city;
      $shipping->state_province = $wc_order->shipping_state;
      $shipping->zip_code = $wc_order->shipping_postcode;
      $shipping->country = $wc_order->shipping_country;
      $shipping->company = $wc_order->shipping_company;
      if($shipping->validate()) {
        $mj_order->set_shipping_address($shipping);
      }
      
      // set order name 
      $mj_order->first_name = $wc_order->billing_first_name;
      $mj_order->last_name = $wc_order->billing_last_name;
      $mj_order->email = $wc_order->billing_email;
      
      // set order totals
      $mj_order->total = $wc_order->get_order_total();
      $mj_order->tax = $wc_order->get_total_tax();
      $mj_order->discount = $wc_order->get_total_discount();
      
      // add meta data to identify woocommerce order
      $mj_order->add_meta_data('wc_order_id', $order_id);
      
      // Set URL for mijireh payment notificatoin
      $mj_order->return_url = trailingslashit(home_url()).'?task=mijireh_notification';
      
      try {
        $mj_order->create();
        $result = array(
          'result' => 'success',
          'redirect' => $mj_order->checkout_url
        );
        return $result;
      }
      catch (Mijireh_Exception $e) {
        $woocommerce->add_error(__('Mijireh error:', 'mijireh_checkout') . $e->getMessage());        
      }
      
    }
    
    public function receipt_page($order) {
			echo '<p>'.__('Thank you for your order.', 'woocommerce').'</p>';
		}
		
	}
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_mijireh_checkout_gateway($methods) {
		$methods[] = 'WC_Mijireh_Checkout';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_mijireh_checkout_gateway' );
}