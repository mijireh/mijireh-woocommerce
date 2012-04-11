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

// Register activation hook to install page slurp page
register_activation_hook(__FILE__, 'install_slurp_page');
register_uninstall_hook(__FILE__, 'remove_slurp_page');

function install_slurp_page() {
  if(!get_page_by_path('mijireh-secure-checkout')) {
    $page = array(
      'post_title' => 'Mijireh Secure Checkout',
      'post_name' => 'mijireh-secure-checkout',
      'post_parent' => 0,
      'post_status' => 'private',
      'post_type' => 'page',
      'comment_status' => 'closed',
      'ping_status' => 'closed',
      'post_content' => "<h1>Checkout</h1>\n\n{{mj-checkout-form}}",
    );
    wp_insert_post($page);
  }
}

function remove_slurp_page() {
  $force_delete = true;
  $post = get_page_by_path('mijireh-secure-checkout');
  wp_delete_post($post->ID, $force_delete);
}

add_action('plugins_loaded', 'woocommerce_mijireh_checkout_init', 0);

function woocommerce_mijireh_checkout_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	load_plugin_textdomain('wc-mijireh-checkout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    
	/**
 	 * Gateway class
 	 */
	class WC_Mijireh_Checkout extends WC_Payment_Gateway {
	  
	  public function __construct() {
	    global $woocommerce;
	    
	    if(!class_exists('Mijireh')) {
	      require 'Mijireh.php';
	    }
	    
  		$this->id = 'mijireh_checkout';
  		$this->icon = apply_filters('mijireh_checkout_icon', WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/credit_cards.png');
      $this->has_fields = false;
      $this->method_title = __( 'Credit Card', 'mijireh_checkout' );
      $this->url = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__));
      
      $this->init_form_fields();
      $this->init_settings();
      
      Mijireh::$access_key = $this->settings['access_key'];
      $this->title = $this->settings['title'];
  		$this->description = $this->settings['description'];
  		
  		
			add_action('init', array(&$this, 'mijireh_notification'));
  		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
  		add_action('add_meta_boxes', array(&$this, 'add_page_slurp_meta'));
  		add_action('wp_ajax_page_slurp', array(&$this, 'page_slurp'));
	  }
	  
	  public function page_slurp() {
	    $page = get_page($_POST['page_id']);
	    $url = get_permalink($page->ID);
      wp_update_post(array('ID' => $page->ID, 'post_status' => 'publish'));
      $job_id = Mijireh::slurp($url);
      wp_update_post(array('ID' => $page->ID, 'post_status' => 'private'));
      echo $job_id;
      die;
	  }
	  
	  public function mijireh_notification() {
	    if(isset($_GET['task']) && $_GET['task'] == 'mijireh_notification') {
	      global $woocommerce;
	      $woocommerce->cart->empty_cart();
	      unset($_SESSION['order_awaiting_payment']);
	      
	      try {
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
					'description' => __( 'Pay securely with your credit card.', 'mijireh_checkout' ), 
					'default' => __( 'Credit Card', 'mijireh_checkout' )
				),
				'description' => array(
  				'title' => __( 'Description', 'mijireh_checkout' ), 
  				'type' => 'textarea', 
  				'description' => __( 'Pay securely with you credit card.', 'mijireh_checkout' ), 
  				'default' => 'Pay securely with your credit card.'
  			),
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
      
      $mj_order = new Mijireh_Order();
      $wc_order = new WC_Order($order_id);
      
      // add items to order
      $items = $wc_order->get_items();
      foreach($items as $item) {
        $product = $wc_order->get_product_from_item($item);
        $mj_order->add_item($item['name'], $wc_order->get_item_total($item), $item['qty'], $product->sku);
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
    
    public function add_page_slurp_meta() { 
      if($this->is_slurp_page()) {
        wp_enqueue_style('mijireh_css', $this->url . '/' . 'mijireh.css');
        wp_enqueue_script('pusher', 'https://d3dy5gmtp8yhk7.cloudfront.net/1.11/pusher.min.js', null, false, true);
        wp_enqueue_script('page_slurp', $this->url . '/' . 'page_slurp.js', array('jquery'), false, true);
        
        add_meta_box(  
          'slurp_meta_box', // $id  
          'Mijireh Page Slurp', // $title  
          array(&$this, 'draw_page_slurp_meta_box'), // $callback  
          'page', // $page  
          'normal', // $context  
          'high'); // $priority  
      }
    }

    public function is_slurp_page() {
      global $post;
      $is_slurp = false;
      if(isset($post) && is_object($post)) {
        $content = $post->post_content;
        if(strpos($content, '{{mj-checkout-form}}') !== false) {
          $is_slurp = true;
        }
      }
      return $is_slurp;
    }

    public function draw_page_slurp_meta_box($post) {
      echo "<div id='mijireh_notice' class='mijireh-info alert-message info' data-alert='alert'>";
      echo  "<div class='mijireh-logo'><img src='" . $this->url . "/images/mijireh-checkout-logo.png' alt='Mijireh Checkout Logo'></div>";
      echo  "<div class='mijireh-blurb'>";
      echo    "<h2>Slurp your custom checkout page!</h2>";
      echo    "<p>Get the page designed just how you want and when you're ready, click the button below and slurp it right up.</p>";
      echo    "<div id='slurp_progress' class='meter progress progress-info progress-striped active' style='display: none;'><div id='slurp_progress_bar' class='bar' style='width: 20%;'>Slurping...</div></div>";
      echo    "<p class='aligncenter'><a href='#' id='page_slurp' rel=". $post->ID ." class='button-primary'>Slurp This Page!</a></p>";
      echo    '<p class="aligncenter"><a class="nobold" href="' . Mijireh::preview_checkout_link() . '" id="view_slurp" target="_new">Preview Checkout Page</a></p>';
      echo  "</div>";
      echo  "</div>";
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
