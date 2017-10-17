<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * TrxServices Payment Gateway
 *
 * @class   WC_Gateway_TrxServices
 * @extends extends WC_Payment_Gateway_CC
 * @version 1.1.0
 */
class WC_Gateway_TrxServices extends WC_Payment_Gateway_CC {

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
    $this->id = 'trxservices';
    $this->icon = apply_filters( 'woocommerce_trxservices_icon', plugins_url( '/assets/images/cards.png', dirname( __FILE__ ) ) );
    $this->has_fields = true;

    $this->order_button_text = __( 'Pay with TrxServices', 'woocommerce-trxservices' );
    $this->method_title = __( 'TrxServices', 'woocommerce-trxservices' );
    $this->method_description = __( 'Take payments via TrxServices.', 'woocommerce-trxservices' );

    $this->supports = array(
      'products',
      'refunds',
      'tokenization'
    );

    $this->view_transaction_url = 'https://manage.trxservices.net';

    // Load the form fields.
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();

    // Load the country codes.
    $this->init_countries();

    // Load the billing periods.
    $this->init_billing_periods();

    // Get settings values.
    $this->enabled        = $this->get_option( 'enabled' );

    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    $this->instructions   = $this->get_option( 'instructions' );
    $this->mode           = $this->get_option( 'mode' ) == 'creditsale' ? 'Sale' : 'Auth';

    $this->sandbox        = $this->get_option( 'sandbox' );

    if ( $this->sandbox == 'no') {
      $this->algorithm_key = $this->get_option( 'algorithm_key' );
      $this->algorithm_iv = $this->get_option( 'algorithm_iv' );
    }

    if ( $this->sandbox == 'yes') {
      $this->algorithm_key = $this->get_option( 'sandbox_algorithm_key' );
      $this->algorithm_iv = $this->get_option( 'sandbox_algorithm_iv' );
    }

    if ( $this->algorithm_key) {
      $this->algorithm_key  = WC_TrxServices_API::hex2bin($this->algorithm_key);
    }
    if ( $this->algorithm_iv) {
      $this->algorithm_iv  = WC_TrxServices_API::hex2bin($this->algorithm_iv);
    }

    $this->debug = $this->get_option( 'debug' );

    if ( is_admin() ) {
      add_action( 'admin_notices', array( $this, 'checks' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
    add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

    // Customer Emails.
    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

    add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
  }

  public function payment_scripts() {
    if ( ! is_cart() && ! is_checkout() && ! is_add_payment_method_page() ) {
      return;
    }
    wp_enqueue_script( 'wc-trxservices', plugins_url('../assets/js/trxservices.js', __FILE__), array( 'wc-credit-card-form' ), WC_TRXSERVICES_VERSION, true );
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

    if ( !is_ssl() && 'yes' != $this->sandbox && 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
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
      $description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date. See the Transaction Services XML API Overview for more card numbers.', 'woocommerce-trxservices' ));
    }

    if ( !empty( $description ) ) {
      echo wpautop( wptexturize( trim( $description ) ) );
    }

    parent::payment_fields();
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
    if ( !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
      if ( !empty( $this->instructions ) ) {
        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
      }
      $this->extra_details( $order->get_id() );
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
   * Add a payment method form.
   */
  public function add_payment_method() {
    if ( empty( $_POST['trxservices_token'] ) || empty( $_POST['trxservices_card_type'] ) || empty( $_POST['trxservices_last_four'] ) || empty( $_POST['trxservices_expiry_month'] ) || empty( $_POST['trxservices_expiry_month'] ) ) {
      wc_add_notice( __( 'There was a problem adding this card.', 'woocommerce-trxservices' ), 'error' );
      return;
    }

    $token_id = $this->create_payment_token();
    if (!$token_id) {
      wc_add_notice( __( 'There was a problem adding this card.', 'woocommerce-trxservices' ), 'error' );
      return;
    }

    return array(
      'result'   => 'success',
      'redirect' => wc_get_endpoint_url( 'payment-methods' ),
    );
  }

  /**
   * Create and save a payment token to the database.
   * @return int Token ID
   */
  public function create_payment_token() {
    $storage_safe_token = wc_clean( $_POST['trxservices_token'] );
    $card_type = wc_clean( $_POST['trxservices_card_type'] );
    $last_four = wc_clean( $_POST['trxservices_last_four'] );
    $expiry_month = wc_clean( $_POST['trxservices_expiry_month'] );
    $expiry_year = wc_clean( $_POST['trxservices_expiry_year'] );

    $token = new WC_Payment_Token_CC();
    $token->set_token( $storage_safe_token );
    $token->set_card_type( $card_type );
    $token->set_last4( $last_four );
    $token->set_expiry_month( $expiry_month );
    $token->set_expiry_year( $expiry_year );
    $token->set_user_id( get_current_user_id() );
    $result = $token->save();
    if ($result) {
      $message = 'Payment token ID %s created for StorageSafe token %s.';
      $this->log( sprintf( __( $message, 'woocommerce-trxservices' ), $result, $storage_safe_token ) );
    }
    return $result;
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
    $storage_safe_token = $order->get_meta( '_trxservices_storage_safe_token', true );

    if (empty($storage_safe_token)) {
      if ( isset( $_POST['wc-trxservices-payment-token'] ) && 'new' !== $_POST['wc-trxservices-payment-token'] ) {
        $token_id = wc_clean( $_POST['wc-trxservices-payment-token'] );
        $token = WC_Payment_Tokens::get( $token_id );

        // Bail if token not owned by current user.
        if ( $token->get_user_id() !== get_current_user_id() ) {
          // Display message to customer.
          $message = __( 'Unable to process payment using this credit card. Please try again.', 'woocommerce-trxservices' );
          wc_add_notice( $message, 'error' );
          return;
        }
        $storage_safe_token = $token->get_token();
      }
    }

    $data = array(
      'Detail' => array(
        'TranType' => 'Credit',
        'TranAction' => $this->mode, // Either Sale or Auth
        'CurrencyCode' => 840, // USD
        'Amount' => $order->get_total(),
      ),
      'IndustryData' => array(
        'Industry' => 'CardNotPresent',
        'Eci' => 7, // One-time secured web-based transaction
      ),
    );

    $account = array(
      'FirstName' => $order->get_billing_first_name(),
      'LastName' => $order->get_billing_last_name(),
      'Email' => $order->get_billing_email()
    );
    // Add optional address fields.
    if (!empty($order->get_billing_postcode())) {
      $account['Postal'] = $order->get_billing_postcode();
    }
    if (!empty($order->get_billing_address_1())) {
      $account['Address'] = $order->get_billing_address_1();
    }
    if (!empty($order->get_billing_address_2())) {
      $account['Address2'] = $order->get_billing_address_2();
    }
    if (!empty($order->get_billing_city())) {
      $account['City'] = $order->get_billing_city();
    }
    if (!empty($order->get_billing_state())) {
      $account['Region'] = $order->get_billing_state();
    }
    // Convert country code to ISO 3166-1 alpha-3.
    $billing_country = !empty($this->countries[$order->get_billing_country()]) ? $this->countries[$order->get_billing_country()] : '';
    if (!empty($billing_country)) {
      $account['Country'] = $billing_country;
    }
    $data['Account']= $account;

    if (!empty($_POST['trxservices_token'])) {
      $storage_safe_token = wc_clean( $_POST['trxservices_token'] );
    }
    $data['StorageSafe']['Guid'] = $storage_safe_token;

    // Bail if no storage safe token.
    if (!$storage_safe_token) {
      return;
    }

    $data = apply_filters( 'woocommerce_trxservices_process_payment', $data, $order );
    $result = WC_TrxServices_API::do_request($data);

    // Bail if payment failed.
    if (!$result) {
      return;
    }

    // Extract guid, responseCode, responseText and (if present) debugInfo.
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
    add_post_meta( $order->get_id(), '_transaction_id', $guid, true );

    // Store transaction type.
    update_post_meta( $order->get_id(), '_trxservices_transaction_type', 'Credit ' . $this->mode );

    // Store StorageSafe token.
    update_post_meta( $order->get_id(), '_trxservices_storage_safe_token', $storage_safe_token );

    // Store token ID.
    if (isset($token_id) && !empty($token_id)) {
      update_post_meta( $order->get_id(), '_trxservices_token_id', $token_id );
    }

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
    $result = WC_TrxServices_API::do_request($data);

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
    update_post_meta( $order->get_id(), '_trxservices_transaction_type', 'Credit ' . $tran_action );

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
    $result = WC_TrxServices_API::do_request($data);

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
    update_post_meta( $order->get_id(), '_trxservices_transaction_type', 'Credit Void' );

    $message = 'TrxServices voided a previous credit capture or sale for order #' . $order->get_id();
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
    $result = WC_TrxServices_API::do_request($data);

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
    update_post_meta( $order->get_id(), '_trxservices_transaction_type', 'Credit Capture' );

    $message = 'TrxServices captured payment for an authorized credit transaction for order #' . $order->get_id();
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
   * Map billing periods to TrxServices RecurPeriod values.
   */
  public function init_billing_periods() {
    $this->recur_periods = array(
      'day' => 'Daily',
      'month' => 'Monthly',
      'year' => 'Yearly',
    );
  }

  /**
   * Log TrxServices events
   *
   * @param  string $message
   */
  public function log( $message ) {
    if ( $this->debug == 'yes' ) {
      $this->log = new WC_Logger();
      $this->log->add( 'woocommerce-' . $this->id, $message );
    }
  }
}
