<?php 
if(!class_exists('Pest')) {
  require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pest.php';
}

class Mijireh_Exception extends Exception {}               
class Mijireh_ClientError extends Mijireh_Exception {}         /* Status: 400-499 */
class Mijireh_BadRequest extends Mijireh_ClientError {}        /* Status: 400 */
class Mijireh_Unauthorized extends Mijireh_ClientError {}      /* Status: 401 */
class Mijireh_NotFound extends Mijireh_ClientError {}          /* Status: 404 */
class Mijireh_ServerError extends Mijireh_Exception {}         /* Status: 500-599 */
class Mijireh_InternalError extends Mijireh_ServerError {}     /* Status: 500 */

class Mijireh {
  public static $url = 'http://mist.mijireh.com/api/1/';
  public static $access_key;
  
  /**
   * Return the job id of the slurp
   */
  public static function slurp($url) {
    $url_format = '/^(https?):\/\/'.                           // protocol
    '(([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+'.         // username
    '(:([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+)?'.      // password
    '@)?(?#'.                                                  // auth requires @
    ')((([a-z0-9][a-z0-9-]*[a-z0-9]\.)*'.                      // domain segments AND
    '[a-z][a-z0-9-]*[a-z0-9]'.                                 // top level domain  OR
    '|((\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])\.){3}'.
    '(\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])'.                 // IP address
    ')(:\d+)?'.                                                // port
    ')(((\/+([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)*'. // path
    '(\?([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)'.      // query string
    '?)?)?'.                                                   // path and query string optional
    '(#([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)?'.      // fragment
    '$/i';
    
    if(!preg_match($url_format, $url)) {
      throw new Mijireh_NotFound('Unable to slurp invalid URL: $url');
    }
    
    try {
      $pest = new Pest($url);
      $html = $pest->get('');
      $data = array(
        'url' => $url,
        'html' => $html,
      );
      $pest = new PestJSON(self::$url);
      $pest->setupAuth(self::$access_key, '');
      $result = $pest->post('slurps', $data);
      return $result['job_id'];
    }
    catch(Pest_Unauthorized $e) {
      throw new Mijireh_Unauthorized("Unauthorized. Please check your api access key");
    }
    catch(Pest_NotFound $e) {
      throw new Mijireh_NotFound("Mijireh resource not found: " . $pest->last_request['url']);
    }
    catch(Pest_ClientError $e) {
      throw new Mijireh_ClientError($e->getMessage());
    }
    catch(Pest_ServerError $e) {
      throw new Mijireh_ServerError($e->getMessage());
    }
    catch(Pest_UnknownResponse $e) {
      throw new Mijireh_Exception('Unable to slurp the URL: $url');
    }
  }
  
  /**
   * Return an array of store information
   */
  public static function get_store_info() {
    $pest = new PestJSON(self::$url);
    $pest->setupAuth(self::$access_key, '');
    try {
      $result = $pest->get('store');
      return $result;
    }
    catch(Pest_BadRequest $e) {
      throw new Mijireh_BadRequest($e->getMessage());
    }
    catch(Pest_Unauthorized $e) {
      throw new Mijireh_Unauthorized("Unauthorized. Please check your api access key");
    }
    catch(Pest_NotFound $e) {
      throw new Mijireh_NotFound("Mijireh resource not found: " . $pest->last_request['url']);
    }
    catch(Pest_ClientError $e) {
      throw new Mijireh_ClientError($e->getMessage());
    }
    catch(Pest_ServerError $e) {
      throw new Mijireh_ServerError($e->getMessage());
    }
  }
}

class Mijireh_Address extends Mijireh_Model {
    
  public function __construct() {
    $this->init();
  }
  
  public function init() {
    $this->_data = array(
      'street' => '',
      'city' => '',
      'state_province' => '',
      'zip_code' => '',
      'country' => '',
      'company' => '',
      'apt_suite' => '',
      'phone' => ''
    );
  }
  
  public function validate() {
    $is_valid = $this->_check_required_fields();
    return $is_valid;
  }
  
  /**
   * Return true if all of the required fields have a non-empty value
   * 
   * @return boolean
   */
  private function _check_required_fields() {
    $pass = true;
    $fields = array('street', 'city', 'state_province', 'zip_code', 'country');
    foreach($fields as $f) {
      if(empty($this->_data[$f])) {
        $pass = false;
        $this->add_error("$f is required");
      }
    }
    return $pass;
  }
  
}

class Mijireh_Item extends Mijireh_Model {
  
  private function _init() {
    $this->_data = array(
      'name' => null,
      'price' => null,
      'quantity' => 1,
      'sku' => null
    );
  }
  
  private function _check_required_fields() {
    if(empty($this->_data['name'])) {
      $this->add_error('item name is required.');
    }
    
    if(!is_numeric($this->_data['price'])) {
      $this->add_error('price must be a number.');
    }
  }
  
  private function _check_quantity() {
    if($this->_data['quantity'] < 1) {
      $this->add_error('quantity must be greater than or equal to 1');
    }
  }
  
  public function __construct() {
    $this->_init();
  }
  
  public function __get($key) {
    $value = false;
    if($key == 'total') {
      $value = $this->_data['price'] * $this->_data['quantity'];
      $value = number_format($value, 2, '.', '');
    }
    else {
      $value = parent::__get($key);
    }
    return $value;
  }
  
  public function get_data() {
    $data = parent::get_data();
    $data['total'] = $this->total;
    return $data;
  }
  
  public function validate() {
    $this->_check_required_fields();
    $this->_check_quantity();
    return count($this->_errors) == 0;
  }
  
}

class Mijireh_Model {
  
  protected $_data = array();
  protected $_errors = array();
  
  /**
   * Set the value of one of the keys in the private $_data array.
   * 
   * @param string $key The key in the $_data array
   * @param string $value The value to assign to the key
   * @return boolean
   */
  public function __set($key, $value) {
    $success = false;
    if(array_key_exists($key, $this->_data)) {
      $this->_data[$key] = $value;
      $success = true;
    }
    return $success;
  }
  
  /**
   * Get the value for the key from the private $_data array.
   * 
   * Return false if the requested key does not exist
   * 
   * @param string $key The key from the $_data array
   * @return mixed
   */
  public function __get($key) {
    $value = false;
    if(array_key_exists($key, $this->_data)) {
      $value = $this->_data[$key];
    }
    
    /*
    elseif(method_exists($this, $key)) {
      $value = call_user_func_array(array($this, $key), func_get_args());
    }
    */
    
    return $value;
  }
  
  /**
   * Return true if the given $key in the private $_data array is set
   * 
   * @param string $key
   * @return boolean   
   */
  public function __isset($key) {
    return isset($this->_data[$key]);
  }
  
  /**
   * Set the value of the $_data array to null for the given key. 
   * 
   * @param string $key
   * @return void
   */
  public function __unset($key) {
    if(array_key_exists($key, $this->_data)) {
      $this->_data[$key] = null;
    }
  }
  
  /**
   * Return the private $_data array
   * 
   * @return mixed
   */
  public function get_data() {
    return $this->_data;
  }
  
  /**
   * Return true if the given $key exists in the private $_data array
   * 
   * @param string $key
   * @return boolean
   */
  public function field_exists($key) {
    return array_key_exists($key, $this->_data);
  }
  
  public function copy_from(array $data) {
    foreach($data as $key => $value) {
      if(array_key_exists($key, $this->_data)) {
        $this->_data[$key] = $value;
      }
    }
  }
  
  public function clear() {
    foreach($this->_data as $key => $value) {
      if($key == 'id') {
        $this->_data[$key] = null;
      }
      else {
        $this->_data[$key] = '';
      }
    }
  }
  
  public function add_error($error_message) {
    if(!empty($error_message)) {
      $this->_errors[] = $error_message;
    }
  }
  
  public function clear_errors() {
    $this->_errors = array();
  }
  
  public function get_errors() {
    return $this->_errors;
  }
  
  public function get_error_lines($glue="\n") {
    $error_lines = '';
    if(count($this->_errors)) {
      $error_lines = implode($glue, $this->_errors);
    }
    return $error_lines;
  }
  
  public function is_valid() {
    return count($this->_errors) == 0;
  }
  
} 

class Mijireh_Order extends Mijireh_Model {
  
  private function _init() {
    $this->_data = array(
      'partner_id' => null,
      'order_number' => null,
      'mode' => null,
      'status' => null,
      'order_date' => null,
      'ip_address' => null,
      'checkout_url' => null,
      'total' => '',
      'return_url' => '',
      'items' => array(),
      'email' => '',
      'first_name' => '',
      'last_name' => '',
      'meta_data' => array(),
      'tax' => '',
      'shipping' => '',
      'discount' => '',
      'shipping_address' => array(),
      'billing_address' => array()
    );
  }
  
  public function __construct($order_number=null) {
    $this->_init();
    if(isset($order_number)) {
      $this->load($order_number);
    }
  }
  
  public function load($order_number) {
    if(strlen(Mijireh::$access_key) < 5) {
      throw new Mijireh_Exception('missing mijireh access key');
    }
    
    $pest = new PestJSON(Mijireh::$url);
    $pest->setupAuth(Mijireh::$access_key, '');
    try {
      $order_data = $pest->get("orders/$order_number");
      $this->copy_from($order_data);
      return $this;
    }
    catch(Pest_BadRequest $e) {
      throw new Mijireh_BadRequest($e->getMessage());
    }
    catch(Pest_Unauthorized $e) {
      throw new Mijireh_Unauthorized("Unauthorized. Please check your api access key");
    }
    catch(Pest_NotFound $e) {
      throw new Mijireh_NotFound("Mijireh resource not found: " . $pest->last_request['url']);
    }
    catch(Pest_ClientError $e) {
      throw new Mijireh_ClientError($e->getMessage());
    }
    catch(Pest_ServerError $e) {
      throw new Mijireh_ServerError($e->getMessage());
    }
  }
  
  public function copy_from($order_data) {
    foreach($order_data as $key => $value) {
      if($key == 'items') {
        if(is_array($value)) {
          $this->clear_items(); // Clear current items before adding new items.
          foreach($value as  $item_array) {
            $item = new Mijireh_Item();
            $item->copy_from($item_array);
            $this->add_item($item);
          }
        }
      }
      elseif($key == 'shipping_address') {
        if(is_array($value)) {
          $address = new Mijireh_Address();
          $address->copy_from($value);
          $this->set_shipping_address($address);
        }
      }
      elseif($key == 'billing_address') {
        if(is_array($value)) {
          $address = new Mijireh_Address();
          $address->copy_from($value);
          $this->set_billing_address($address);
        }
      }
      elseif($key == 'meta_data') {
        if(is_array($value)) {
          $this->clear_meta_data(); // Clear current meta data before adding new meta data
          $this->_data['meta_data'] = $value;
        }
      }
      else {
        $this->$key = $value;
      }
    }
    
    if(!$this->validate()) {
      throw new Mijireh_Exception('invalid order hydration: ' . $this->get_errors_lines());
    }
    
    return $this;
  }
  
  public function create() {
    if(strlen(Mijireh::$access_key) < 5) {
      throw new Mijireh_Exception('missing mijireh access key');
    }
    
    if(!$this->validate()) {
      $error_message = 'unable to create order: ' . $this->get_error_lines();
      throw new Mijireh_Exception($error_message);
    }
    
    $pest = new PestJSON(Mijireh::$url);
    $pest->setupAuth(Mijireh::$access_key, '');
    try {
      $result = $pest->post('orders', $this->get_data());
      $this->copy_from($result);
      return $this;
    }
    catch(Pest_BadRequest $e) {
      throw new Mijireh_BadRequest($e->getMessage());
    }
    catch(Pest_Unauthorized $e) {
      throw new Mijireh_Unauthorized("Unauthorized. Please check your api access key");
    }
    catch(Pest_NotFound $e) {
      throw new Mijireh_NotFound("Mijireh resource not found: " . $pest->last_request['url']);
    }
    catch(Pest_ClientError $e) {
      throw new Mijireh_ClientError($e->getMessage());
    }
    catch(Pest_ServerError $e) {
      throw new Mijireh_ServerError($e->getMessage());
    }
  }
  
  /**
   * If meta_data or shipping_address are empty, exclude them altogether.
   */
  public function get_data() {
    $data = parent::get_data();
    if(count($data['meta_data']) == 0) { unset($data['meta_data']); }
    if(count($data['shipping_address']) == 0) { unset($data['shipping_address']); }
    return $data;
  }
  
  /**
   * Add the specified item and price to the order.
   * 
   * Return the total number of items in the order (including the one that was just added)
   * 
   * @return int
   */
  public function add_item($name, $price=0, $quantity=1, $sku='') {
    $item = '';
    if(is_object($name) && get_class($name) == 'Mijireh_Item') {
      $item = $name;
    }
    else {
      $item = new Mijireh_Item();
      $item->name = $name;
      $item->price = $price;
      $item->quantity = $quantity;
      $item->sku = $sku;
    }
    
    if($item->validate()) {
      $this->_data['items'][] = $item->get_data();
      return $this->item_count();
    }
    else {
      $errors = implode(' ', $item->get_errors());
      throw new Mijireh_Exception('unable to add invalid item to order :: ' . $errors);
    }
  }
  
  public function add_meta_data($key, $value) {
    if(!is_array($this->_data['meta_data'])) {
      $this->_data['meta_data'] = array();
    }
    $this->_data['meta_data'][$key] = $value;
  }
  
  /**
   * Return the value associated with the given key in the order's meta data.
   * 
   * If the key does not exist, return false.
   */
  public function get_meta_value($key) {
    $value = fasle;
    if(isset($this->_data['meta_data'][$key])) {
      $value = $this->_data['meta_data'][$key];
    }
    return $value;
  }
  
  public function item_count() {
    $item_count = 0;
    if(is_array($this->_data['items'])) {
      $item_count = count($this->_data['items']);
    }
    return $item_count;
  }
  
  public function get_items() {
    $items = array();
    foreach($this->_data['items'] as $item_data) {
      $item = new Mijireh_Item();
      $item->copy_from($item_data);
    }
  }
  
  public function clear_items() {
    $this->_data['items'] = array();
  }
  
  public function clear_meta_data() {
    $this->_data['meta_data'] = array();
  }
  
  public function validate() {
    $this->_check_total();
    $this->_check_return_url();
    $this->_check_items();
    return count($this->_errors) == 0;
  }
  
  /**
   * Alias for set_shipping_address()
   */
  public function set_address(Mijireh_Address $address){ 
    $this->set_shipping_address($address);
  }
  
  public function set_shipping_address(Mijireh_Address $address) {
    if($address->validate()) {
      $this->_data['shipping_address'] = $address->get_data();
    }
    else {
      throw new Mijireh_Exception('invalid shipping address');
    }
  }
  
  public function set_billing_address(Mijireh_Address $address) {
    if($address->validate()) {
      $this->_data['billing_address'] = $address->get_data();
    }
    else {
      throw new Mijireh_Exception('invalid shipping address');
    }
  }
  
  /**
   * Alias for get_shipping_address()
   */
  public function get_address() {
    return $this->get_shipping_address();
  }
  
  public function get_shipping_address() {
    $address = false;
    if(is_array($this->_data['shipping_address'])) {
      $address = new Mijireh_Address();
      $address->copy_from($this->_data['shipping_address']);
    }
    return $address;
  }
  
  public function get_billing_address() {
    $address = false;
    if(is_array($this->_data['billing_address'])) {
      $address = new Mijireh_Address();
      $address->copy_from($this->_data['billing_address']);
    }
    return $address;
  }
  
  /**
   * The order total must be greater than zero.
   * 
   * Return true if valid, otherwise false.
   * 
   * @return boolean
   */
  private function _check_total() {
    $is_valid = true;
    if($this->_data['total'] <= 0) {
      $this->add_error('order total must be greater than zero');
      $is_valid = false;
    }
    return $is_valid;
  }
  
  /**
   * The return url must be provided and must start with http.
   * 
   * Return true if valid, otherwise false
   * 
   * @return boolean
   */
  private function _check_return_url() {
    $is_valid = false;
    if(!empty($this->_data['return_url'])) {
      $url = $this->_data['return_url'];
      if('http' == strtolower(substr($url, 0, 4))) {
        $is_valid = true;
      }
      else {
        $this->add_error('return url is invalid');
      }
    }
    else {
      $this->add_error('return url is required');
    }
    return $is_valid;
  }
  
  /**
   * An order must contain at least one item
   * 
   * Return true if the order has at least one item, otherwise false.
   * 
   * @return boolean
   */
  private function _check_items() {
    $is_valid = true;
    if(count($this->_data['items']) <= 0) {
      $is_valid = false;
      $this->add_error('the order must contain at least one item');
    }
    return $is_valid;
  }
  
}