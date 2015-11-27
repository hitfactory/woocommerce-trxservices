<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * TrxServices Payment Gateway
 *
 * @class   WC_Gateway_TrxServices
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 */
class WC_Gateway_TrxServices extends WC_Payment_Gateway {

  /**
     * Instance of this class.
     *
     * @access protected
     * @access static
     * @var object
     */
  protected static $instance = null;

  /**
   * Main WC_Gateway_TrxServices Instance
   *
   * Ensures only one instance of WC_Gateway_TrxServices is loaded or can be loaded.
   *
   * @static
   * @see WC_Gateway_TrxServices()
   * @return WC_Gateway_TrxServices
   */
  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Constructor for the gateway.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    $this->id                 = 'trxservices';
    $this->icon               = apply_filters( 'woocommerce_trxservices_icon', plugins_url( '/assets/images/cards.png', dirname( __FILE__ ) ) );
    $this->has_fields         = true;

    $this->order_button_text  = __( 'Pay with TrxServices', 'woocommerce-trxservices' );
    $this->method_title       = __( 'TrxServices', 'woocommerce-trxservices' );
    $this->method_description = __( 'Take payments via TrxServices.', 'woocommerce-trxservices' );

    $this->supports           = array(
      'products',
      'default_credit_card_form',
      'refunds',
    );

    $this->view_transaction_url = '';

    // Load the form fields.
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();

    // Load the country codes.
    $this->init_countries();

    // Get settings values.
    $this->enabled        = $this->get_option( 'enabled' );

    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    $this->instructions   = $this->get_option( 'instructions' );
    $this->mode           = $this->get_option( 'mode' ) == 'creditsale' ? 'Sale' : 'Auth';

    $this->sandbox        = $this->get_option( 'sandbox' );

    $this->api_endpoint   = $this->sandbox == 'no' ? 'https://api.trxservices.com/' :  'https://api.trxservices.net/';

    if ( $this->sandbox == 'no') {
      $this->algorithm = defined( 'TRXSERVICES_ALGORITHM' ) ? TRXSERVICES_ALGORITHM : '';
      $this->algorithm_mode = defined( 'TRXSERVICES_ALGORITHM_MODE' ) ? TRXSERVICES_ALGORITHM_MODE : '';
      $this->algorithm_key = defined( 'TRXSERVICES_ALGORITHM_KEY' ) ? TRXSERVICES_ALGORITHM_KEY : '';
      $this->algorithm_iv = defined( 'TRXSERVICES_ALGORITHM_IV' ) ? TRXSERVICES_ALGORITHM_IV : '';
      $this->client = defined( 'TRXSERVICES_CLIENT' ) ? TRXSERVICES_CLIENT : '';
      $this->source = defined( 'TRXSERVICES_SOURCE' ) ? TRXSERVICES_SOURCE : '';
    }

    if ( $this->sandbox == 'yes') {
      $this->algorithm = defined( 'TRXSERVICES_SANDBOX_ALGORITHM' ) ? TRXSERVICES_SANDBOX_ALGORITHM : '';
      $this->algorithm_mode = defined( 'TRXSERVICES_SANDBOX_ALGORITHM_MODE' ) ? TRXSERVICES_SANDBOX_ALGORITHM_MODE : '';
      $this->algorithm_key = defined( 'TRXSERVICES_SANDBOX_ALGORITHM_KEY' ) ? TRXSERVICES_SANDBOX_ALGORITHM_KEY : '';
      $this->algorithm_iv = defined( 'TRXSERVICES_SANDBOX_ALGORITHM_IV' ) ? TRXSERVICES_SANDBOX_ALGORITHM_IV : '';
      $this->client = defined( 'TRXSERVICES_SANDBOX_CLIENT' ) ? TRXSERVICES_SANDBOX_CLIENT : '';
      $this->source = defined( 'TRXSERVICES_SANDBOX_SOURCE' ) ? TRXSERVICES_SANDBOX_SOURCE : '';
    } 

    if ( $this->algorithm_key) {
      $this->algorithm_key  = $this->hex2bin($this->algorithm_key);
    }
    if ( $this->algorithm_iv) {
      $this->algorithm_iv  = $this->hex2bin($this->algorithm_iv);
    }

    $this->debug          = $this->get_option( 'debug' );

    if ( is_admin() ) {
      add_action( 'admin_notices', array( $this, 'checks' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
    add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

    // Customer Emails.
    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
  }

  /**
   * Check if this gateway can be enabled.
   * 
   * @return bool
   */
  public function can_be_enabled() {
    return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_trxservices_supported_currencies', array( 'USD' ) ) );
  }

  /**
   * Admin Panel Options
   *
   * @access public
   * @return void
   */
  public function admin_options() {
    include_once( WC_TrxServices()->plugin_path() . '/includes/admin/views/admin-options.php' );
  }

  /**
   * Check if SSL is enabled and notify the user.
   *
   * @access public
   */
  public function checks() {

    if ( $this->enabled == 'no' ) {
      return;
    }

    // Supported currencies.
    if ( $this->can_be_enabled() == false ) {
      echo '<div class="error"><p>' .  __( 'TrxServices is enabled, but it does not support your store currency.', 'woocommerce-trxservices' ) . '</p></div>';
    }

    // PHP Version.
    if ( version_compare( phpversion(), '5.3', '<' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'TrxServices requires PHP 5.3 and above. You are using version %s.', 'woocommerce-trxservices' ), phpversion() ) . '</p></div>';
    }

    // Check test credentials are set.
    if ( $this->sandbox == 'yes') {
      if ( !defined( 'TRXSERVICES_SANDBOX_ALGORITHM' ) || !defined( 'TRXSERVICES_SANDBOX_ALGORITHM_MODE' )
        || !defined( 'TRXSERVICES_SANDBOX_ALGORITHM_KEY' ) || !defined( 'TRXSERVICES_SANDBOX_ALGORITHM_IV' )
        || !defined( 'TRXSERVICES_SANDBOX_CLIENT' ) ||  !defined( 'TRXSERVICES_SANDBOX_SOURCE' ) ) {
        echo '<div class="error"><p>' . __( 'TrxServices Error: Please set your test credentails in wp-config.php.', 'woocommerce-trxservices' ) . '</p></div>';
      }
    }

    // Check production credentials are set.
    if ( $this->sandbox == 'no') {
      if ( !defined( 'TRXSERVICES_ALGORITHM' ) || !defined( 'TRXSERVICES_ALGORITHM_MODE' )
        || !defined( 'TRXSERVICES_ALGORITHM_KEY' ) || !defined( 'TRXSERVICES_ALGORITHM_IV' )
        || !defined( 'TRXSERVICES_CLIENT' ) ||  !defined( 'TRXSERVICES_SOURCE' ) ) {
        echo '<div class="error"><p>' . __( 'TrxServices Error: Please set your production credentials in wp-config.php.', 'woocommerce-trxservices' ) . '</p></div>';
      }
    }

    // Show message if logging is enabled and not in sandbox mode.
    if ( $this->debug == 'yes' && $this->sandbox == 'no' ) {
      echo '<div class="error"><p>' . sprintf( __( 'TrxServices Error: The Debug Log should be disabled when not in sandbox mode. You are currently exposing customer data!', 'woocommerce-trxservices'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
    }

    // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
    if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) && !class_exists( 'WordPressHTTPS' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'TrxServices is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - TrxServices will only work in sandbox mode.', 'woocommerce-trxservices'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
    }
  }

  /**
   * Check if this gateway is enabled.
   *
   * @access public
   */
  public function is_available() {
    if ( $this->enabled == 'no' ) {
      return false;
    }

    if ( !$this->algorithm_iv || !$this->algorithm_key ) {
      return false;
    }

    return true;
  }

  /**
   * Initialise Gateway Settings Form Fields
   *
   * @access public
   */
  public function init_form_fields() {
    $this->form_fields = include( WC_TrxServices()->plugin_path() . '/includes/settings-trxservices.php' );
  }

  /**
   * Output for the order received page.
   *
   * @param int $order_id Order ID
   */
  public function receipt_page( $order_id ) {
    echo '<p>' . __( 'Thank you for your order.', 'woocommerce-trxservices' ) . '</p>';
  }

  /**
   * Payment form on checkout page.
   *
   * @access public
   */
  public function payment_fields() {
    $description = $this->get_description();

    if ( $this->sandbox == 'yes' ) {
      $description .= ' ' . __( 'TEST MODE ENABLED.' );
    }

    if ( !empty( $description ) ) {
      echo wpautop( wptexturize( trim( $description ) ) );
    }

    // Display default credit card form.
    if ( $this->supports( 'default_credit_card_form' ) ) {
      $this->credit_card_form(
        array( 
          'fields_have_names' => true
        )
      );
    }

    // Include custom payment fields.
    include_once( WC_TrxServices()->plugin_path() . '/includes/views/html-payment-fields.php' );
  }

  /**
   * Output for the order received page.
   *
   * @access public
   */
  public function thankyou_page( $order_id ) {
    if ( !empty( $this->instructions ) ) {
      echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
    }

    $this->extra_details( $order_id );
  }

  /**
   * Add content to the WC emails.
   *
   * @access public
   * @param  WC_Order $order
   * @param  bool $sent_to_admin
   * @param  bool $plain_text
   */
  public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if ( !$sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
      if ( !empty( $this->instructions ) ) {
        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
      }
      $this->extra_details( $order->id );
    }
  }

  /**
   * Extra content to be displayed on the 'Thank you' page.
   *
   * @access private
   */
  private function extra_details( $order_id = '' ) {
  }

  /**
   * Process a credit card payment
   *
   * @access public
   * @param  int $order_id
   * @return bool | array
   */
  public function process_payment( $order_id ) {
    
    $order = new WC_Order( $order_id );

    $pan_number = isset($_POST['trxservices-card-number']) ? woocommerce_clean($_POST['trxservices-card-number']) : '';
    $pan_number = str_replace(' ', '', $pan_number);
    
    $card_cvv = isset($_POST['trxservices-card-cvc']) ? woocommerce_clean($_POST['trxservices-card-cvc']) : '';
    $card_expiry = isset($_POST['trxservices-card-expiry']) ? woocommerce_clean($_POST['trxservices-card-expiry']) : '';
    $card_expiry = str_replace(array(' ', '/'), '' , $card_expiry);
    
    // Convert country code to ISO 3166-1 alpha-3.
    $billing_country = !empty($this->countries[$order->billing_country]) ? $this->countries[$order->billing_country] : '';
                    
    $data = array(
      'Detail' => array(
        'TranType' => 'Credit',
        'TranAction' => $this->mode, // Either Sale or Auth
        'CurrencyCode' => 840, // USD
        'Amount' => $order->order_total,
      ),
      'Account' => array(
        'FirstName' => $order->billing_first_name,
        'LastName' => $order->billing_last_name,
        'Email' => $order->billing_email,
        'Pan' => $pan_number,
        'Cvv' => $card_cvv,
        'Expiration' => $card_expiry,
        'Postal' => $order->billing_postcode,
        'Address' => $order->billing_address_1,
        'City' => $order->billing_city,
        'Region' =>  $order->billing_state, 
        'Country' =>  $billing_country,
      ),
      'IndustryData' => array(
        'Industry' => 'CardNotPresent',
        'Eci' => 7, // One-time secured web-based transaction
      ),
    );

    // Add optional fields.
    if (!empty($order->billing_address_2)) {
      $data['Account']['Address2'] = $order->billing_address_2;
    }

    $data = apply_filters( 'woocommerce_trxservices_payment_data', $data, $order );
    $result = $this->do_request($data);
    
    // Bail if payment failed.
    if (!$result) {
      return;
    }
     
    // Extract guid, responseCode, responseText 
    // and (if present) debugInfo.
    extract($result); 

    // Bail if payment failed.
    if ($responseCode != '00') {
      $message = $this->mode == 'Sale' 
        ? 'TrxServices credit sale payment failed (GUID: %s ResponseCode: %s ResponseText: %s)' 
        : 'TrxServices credit authorization failed (GUID: %s ResponseCode: %s ResponseText: %s)';
      $message = sprintf( __( $message, 'woocommerce-trxservices' ), $guid, $responseCode, $responseText  );
      $order->add_order_note( $message );
      $this->log( $message );
      // Display message to customer.
      $message = __( 'Unable to process payment. Please try again.', 'woocommerce-trxservices' );
      wc_add_notice( $message, 'error' );
      return;
    }
    
    // Store GUID as transaction ID.
    add_post_meta( $order->id, '_transaction_id', $guid, true );

    // Store transaction type.
    update_post_meta( $order->id, '_trxservices_transaction_type', 'Credit ' . $this->mode );

    // Add order note.
    $message = $this->mode == 'Sale' ? 'TrxServices credit sale payment approved (GUID: %s)' : 'TrxServices credit authorization approved (GUID: %s)';
    $order->add_order_note( sprintf( __( $message, 'woocommerce-trxservices' ), $guid ) );
    $this->log( sprintf( __( $message, 'woocommerce-trxservices' ), $guid ) );

    if ($this->mode == 'Sale') {
      $order->payment_complete();
    }
    else {
      $order->update_status( 'on-hold', 
      __( 'On-Hold', 'woocommerce-trxservices' ) );
    }
    
    // Remove items from cart.
    WC()->cart->empty_cart();
    
    // Return result and redirect to thank you page.
    return array(
      'id' => $guid,
      'result'   => 'success',
      'redirect' => $this->get_return_url( $order ),
    );
  }

  /**
   * Process a refund.
   *
   * @access public
   * @param  int $order_id
   * @param  float $amount
   * @param  string $reason
   * @return bool|WP_Error
   */
  public function process_refund( $order_id, $amount = null, $reason = '' ) {

    $order = wc_get_order( $order_id );

    // Bail if no GUID.
    if (!$order || !$order->get_transaction_id()) {
      $this->log( 'TrxServices refund failed: No GUID' );
      return false;
    }

    $guid = $order->get_transaction_id();
    $transaction_type = get_post_meta( $order_id, '_trxservices_transaction_type', true );
    $tran_action = $transaction_type == 'Credit Sale' ? 'Return' : 'Void';

    $data = array(
      'Detail' => array(
        'TranType' => 'Credit',
        'TranAction' => $tran_action,
        'CurrencyCode' => 840, // USD
        'Amount' => $amount,
      ),
      'Reference' => array(
        'Guid' => $guid,
      ),
    );

    $data = apply_filters( 'woocommerce_trxservices_refund_data', $data, $order, $amount, $reason );
    $result = $this->do_request($data);
    
    // Bail if refund failed.
    if (!$result) {
      return false;
    }
     
    // Extract guid, responseCode and responseText.
    extract($result); 

    if ( $responseCode != '00' ) {
      $message = sprintf( __( 'Unable to refund order %d via TrxServices (GUID: %s ResponseCode: %s ResponseText: %s)', 
        'woocommerce-trxservices' ), $order_id, $guid, $responseCode, $responseText  );
      $order->add_order_note( $message );
      $this->log( $message );
      return false;
    }

    // Mark order as refunded.
    $order->update_status( 'refunded', __( 'Order refunded via TrxServices.', 'woocommerce-trxservices' ) );
    $order->add_order_note( sprintf( __( 'Refunded %s (GUID: %s)', 'woocommerce-trxservices' ), $amount, $guid ) );

     // Store transaction type.
    update_post_meta( $order->id, '_trxservices_transaction_type', 'Credit ' . $tran_action );

    $message = sprintf( __( 'TrxServices order #%s refunded.', 'woocommerce-trxservices' ), $order_id ); 
    $this->log( $message );
    
    return true;
  }

  /**
   * Process a Credit Void.
   *
   * @access public
   * @param  WC_Order $order
   * @return bool|WP_Error
   */
  public function process_creditvoid( $order ) {

    // Bail if no GUID.
    if (!$order || !$order->get_transaction_id()) {
      $this->log( 'TrxServices failed to void a previous credit capture or sale: No GUID' );
      return false;
    }

    $guid = $order->get_transaction_id();
    
    $data = array(
      'Detail' => array(
        'TranType' => 'Credit',
        'TranAction' => 'Void',
      ),
      'Reference' => array(
        'Guid' => $guid,
      ),
    );

    $data = apply_filters( 'woocommerce_trxservices_creditvoid_data', $data, $order, $amount, $reason );
    $result = $this->do_request($data);
    
    // Bail if void failed.
    if (!$result) {
      return false;
    }
     
    // Extract guid, responseCode and responseText.
    extract($result);
    
    if ( $responseCode != '00' ) {
      $message = sprintf( __( 'Unable to void previous credit capture or sale for order %d via TrxServices (GUID: %s ResponseCode: %s ResponseText: %s)', 
        'woocommerce-trxservices' ), $order_id, $guid, $responseCode, $responseText  );
      $order->add_order_note( $message );
      $this->log( $message );
      return false;
    }

    // Change order status.
    $order->update_status( 'cancelled', __( 'Voided a previous credit capture or sale via TrxServices.', 'woocommerce-trxservices' ) );
    $order->add_order_note( sprintf( __( 'Voided a previous credit capture or sale via TrxServices %s (GUID: %s)', 'woocommerce-trxservices' ), $amount, $guid ) );

    // Store transaction type.
    update_post_meta( $order->id, '_trxservices_transaction_type', 'Credit Void' );

    $message = 'TrxServices voided a previous credit capture or sale for order #' . $order->id;
    $this->log( $message );
    
    return true;
  }

  /**
   * Process a Credit Capture transaction.
   *
   * @access public
   * @param  WC_Order $order
   * @return bool|WP_Error
   */
  public function process_creditcapture( $order ) {
    
    // Bail if no GUID.
    if (!$order || !$order->get_transaction_id()) {
      $this->log( 'TrxServices failed to capture an authorized credit transaction: No GUID' );
      return false;
    }

    $guid = $order->get_transaction_id();
    
    $data = array(
      'Detail' => array(
        'TranType' => 'Credit',
        'TranAction' => 'Capture',
        'CurrencyCode' => 840, // USD
        'Amount' => $order->order_total,
      ),
      'Reference' => array(
        'Guid' => $guid,
      ),
    );

    $data = apply_filters( 'woocommerce_trxservices_creditcapture_data', $data, $order, $amount, $reason );
    $result = $this->do_request($data);
    
    // Bail if capture failed.
    if (!$result) {
      return false;
    }
     
    // Extract guid, responseCode and responseText.
    extract($result);
    
    if ( $responseCode != '00' ) {
      $message = sprintf( __( 'Unable to capture payment for order %s via TrxServices (GUID: %s ResponseCode: %s ResponseText: %s)', 
        'woocommerce-trxservices' ), $order_id, $guid, $responseCode, $responseText  );
      $order->add_order_note( $message );
      $this->log( $message );
      return false;
    }

    // Change order status.
    $order->update_status( 'processing', __( 'Payment captured via TrxServices.', 'woocommerce-trxservices' ) );
    $order->add_order_note( sprintf( __( 'TrxServices Credit Capture %s (GUID: %s)', 'woocommerce-trxservices' ), $amount, $guid ) );

     // Store transaction type.
    update_post_meta( $order->id, '_trxservices_transaction_type', 'Credit Capture' );

    $message = 'TrxServices captured payment for an authorized credit transaction for order #' . $order->id;
    $this->log( $message );
    return true;
  }

  /**
   * Initialise country codes.
   */
  public function init_countries() {
    $this->countries = include( 'iso3166-country-codes.php' );
  }

  /**
   * Build XMLRequest string
   * 
   * @param  array $data 
   * @return string $xmlRequest
   */
  public function build_xmlrequest($data) {
    $xml = '';
    foreach($data as $child => $elements) {
      $xml .= "<$child>";
      foreach($elements as $key => $value) {
        $xml .= "<$key>" . htmlspecialchars(trim($value)) . "</$key>";
      }
      $xml .= "</$child>";
    }

    $message = 'TrxServices Request: ' . print_r( $xml, true ) . ')';
    $this->log( $message );

    // Encrypt Request.
    $Request =  $this->encrypt($xml);

    $xmlRequest  = '<Message>';
    $xmlRequest .= '<Request>'. $Request .'</Request>';                                
    $xmlRequest .= '<Authentication>';
    $xmlRequest .= '<Client>' . $this->client . '</Client>';
    $xmlRequest .= '<Source>' . $this->source . '</Source>';
    $xmlRequest .= '</Authentication>';
    $xmlRequest .= '</Message>';
    return $xmlRequest;
  }

  /**
   * Make API request
   * 
   * @param array $data
   * @return bool | array
   */
  public function do_request($data) {
    $xmlRequest = $this->build_xmlrequest($data);
    try {
      $response = wp_remote_post($this->api_endpoint, array(
        'method' => 'POST',
        'body' => $xmlRequest,
        'timeout' => 15,
        'headers' => array('Content-Type' => 'text/xml'),
      ));

      if (is_wp_error($response)) {
        $this->log( 'WP_Error: ' . print_r($response, TRUE) );
        throw new Exception(__('Unable to connect to TrxServices. Please try again.', 'woocommerce-trxservices'));
      }
    }
    catch (Exception $e) {
      $message = sprintf( __( '%s', 
        'woocommerce-trxservices' ), $e->getMessage()  );
      $this->log( $message );
      wc_add_notice( $message, 'error' );
      return;
    }
     
    // Parse the XML response.
    $result = $this->parse_response($response);
    return $result;
  }

  /**
   * Parse response body
   * 
   * @param  array $response 
   * @return array $result contains keys guid, responseCode and responseText          
   */
  public function parse_response($response) {
    $xmlResponse = simplexml_load_string($response['body']);

    // Existence of Result property indicates failure.
    if ( isset($xmlResponse->Response->Result) ) {
      $responseCode = (string) $xmlResponse->Response->Result->ResponseCode;
      $guid = (string) $xmlResponse->Response->Reference->Guid;
      $responseText = (string) $xmlResponse->Response->Result->ResponseText;
    }
    else {
      // On success, XML will contain encrypted response.
      $responseString = (string) $xmlResponse->Response[0];
      $response = $this->decrypt($responseString);

      $message = 'TrxServices Response: ' . print_r($response, true);
      $this->log( $message );
      
      // Parse the decoded XML response.
      $response = "<Message>$response</Message>";
      $xmlResponse = simplexml_load_string($response);
      $guid = (string) $xmlResponse->Reference->Guid[0];
      $responseCode = (string) $xmlResponse->Result->ResponseCode;
      $responseText = (string) $xmlResponse->Result->ResponseText;
      if (isset($xmlResponse->Result->DebugInfo)) {
        $debugInfo = (string) $xmlResponse->Result->DebugInfo;
      }
    }
    
    $result = compact('guid', 'responseCode', 'responseText', 'debugInfo');
    return $result;
  }

  /**
   * Decodes a hexadecimally encoded binary string
   *
   * @param  string $str Hexadecimally encoded binary string
   * @return string $bin Binary string
   */
  public function hex2bin($str) {
    $bin = "";
    $i = 0;
    do {
        $bin .= chr(hexdec($str{$i}.$str{($i + 1)}));
        $i += 2;
    } while ($i < strlen($str));
    return $bin;
  }

  /**
   * Encrypt a value using AES 256 encryption in CBC mode.
   * 
   * @param  string $value Value to be encrypted
   * @return string Encrypted value.
   */
  public function encrypt($value) {
    $AES  = new AES_Encryption($this->algorithm_key, $this->algorithm_iv);
    $encrypted = $AES->encrypt($value);
    return base64_encode($encrypted);
  }

  /**
   * Decrypt a value encyrpted using AES 256 encryption in CBC mode.
   * 
   * @param  string $value Value to be decrypted
   * @return string Decrypted value.
   */
  public function decrypt($value) {
    $AES  = new AES_Encryption($this->algorithm_key, $this->algorithm_iv);
    return $AES->decrypt(base64_decode($value));
  }

  /**
   * Log TrxServices events
   * 
   * @param  string $message
   */
  public function log( $message ) {
    if ( $this->debug == 'yes' ) {
      $this->log = new WC_Logger();
      $this->log->add( $this->id, $message );
    }
  }

}