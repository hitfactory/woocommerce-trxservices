<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * WC_Gateway_TrxServices_Addons class adds support for subscriptions.
 *
 * @extends WC_Gateway_TrxServices
 */
class WC_Gateway_TrxServices_Addons extends WC_Gateway_TrxServices {

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct();

    $this->supports = array_merge(
    $this->supports,
      array(
        'subscriptions',
        'subscription_cancellation',
        'subscription_suspension',
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
        'subscription_payment_method_change_customer',
        'subscription_payment_method_change_admin',
        'multiple_subscriptions',
        'tokenization',
        'add_payment_method'
      )
    );

    if ( class_exists( 'WC_Subscriptions_Order' ) ) {
      add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
      add_action( 'woocommerce_subscription_failing_payment_method_updated_trxservices', array( $this, 'update_failing_payment_method' ), 10, 2 );
      add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'add_subscription_payment_method' ), 10, 2 );
      add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
      //add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
    }
  }

  /**
   * Checks whether order contains subscription.
   *
   * @param int $order_id Order ID
   *
   * @return bool Returns true if order contains subscription
   */
  public function order_has_subscription( $order_id ) {
    return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
  }

  /**
   * Process order payment based on whether it contains a subscription
   *
   * @param  int $order_id
   * @return array
   */
  public function process_payment( $order_id ) {
    if ( $this->order_has_subscription( $order_id ) ) {
      $this->process_subscription( $order_id );
      // Finish processing order as a regular payment.
      return parent::process_payment( $order_id );
    } else {
      return parent::process_payment( $order_id );
    }
  }

  /**
   * Process order containing a subscription
   *
   * @access public
   * @param  int $order_id
   * @return bool | array
   */
  public function process_subscription( $order_id ) {
    $order = new WC_Order( $order_id );
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

    if (empty($storage_safe_token)) {
      if (!empty($_POST['trxservices_token'])) {
        $storage_safe_token = wc_clean( $_POST['trxservices_token'] );
        $token_id = $this->create_payment_token();
        if (!$token_id) {
          wc_add_notice( __( 'Unable to process payment using this credit card. Please try again.', 'woocommerce-trxservices' ), 'error' );
          return;
        }
        update_post_meta( $order_id, '_trxservices_token_id', $token_id);
      }
    }

    update_post_meta( $order_id, '_trxservices_storage_safe_token', $storage_safe_token);

    // Fetch all subscriptions in this order.
    $subscriptions = array();
    if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
      $subscriptions = wcs_get_subscriptions_for_order( $order_id );
    } elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
      $subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
    }

    // Update each subscription.
    foreach($subscriptions as $subscription) {
      $subscription_id = $subscription->get_id();
      update_post_meta( $subscription_id, '_trxservices_storage_safe_token', $storage_safe_token);
      update_post_meta( $subscription_id, '_trxservices_account_token', $order->get_meta( '_trxservices_account_token', true ));
    }
  }

  /**
   * Process scheduled subscription payment.
   *
   * @since 1.1.0
   *
   * @param float        $amount Subscription amount
   * @param int|WC_Order $order  Order ID or order object
   */
  public function scheduled_subscription_payment( $amount, $order ) {
    $this->log( 'About to process scheduled subscription payment' );
    $order = wc_get_order( $order );
    $storage_safe_token = $order->get_meta( '_trxservices_storage_safe_token', true );

    if ( empty( $storage_safe_token ) ) {
      $this->log( 'No StorageSafe token so skipping scheduled subscription payment.' );
      return;
    }

    if ( 0 == $amount ) {
      $order->payment_complete();
      return;
    }

    return $this->process_subscription_payment($amount, $order);
  }

  /**
   * Process a scheduled subscription payment.
   *
   * @param float        $amount Subscription amount
   * @param int|WC_Order $order  Order ID or order object
   */
  public function process_subscription_payment( $amount, $order) {
    $storage_safe_token = $order->get_meta( '_trxservices_storage_safe_token', true );
    $message = 'About to process subscription payment using StorageSafe token %s';
    $message = sprintf( __( $message, 'woocommerce-trxservices' ), $storage_safe_token );
    $this->log( $message );
    $data = array(
      'Detail' => array(
        'TranType' => 'Credit',
        'TranAction' => $this->mode, // Either Sale or Auth
        'CurrencyCode' => 840, // USD
        'Amount' => $amount,
        'Ip' => WC_Geolocation::get_ip_address(),
      ),
      'StorageSafe' => array(
        'Guid' => $storage_safe_token,
      ),
      'IndustryData' => array(
        'Industry' => 'CardNotPresent',
        'Eci' => 7, // One-time secured web-based transaction
      ),
    );

    $account_token == $order->get_meta( '_trxservices_account_token', true );
    if ($account_token) {
      $data['Account']['Guid'] = $account_token;
    }

    $result = WC_TrxServices_API::do_request($data);

    // Bail if payment failed.
    if (!$result) {
      return;
    }

    // Extract guid, responseCode, responseText and (if present) debugInfo.
    extract($result);

    // Bail if payment failed.
    if ($responseCode != '00') {
      $message = 'TrxServices scheduled subscription payment failed (GUID: %s ResponseCode: %s ResponseText: %s)';
      $message = sprintf( __( $message, 'woocommerce-trxservices' ), $guid, $responseCode, $responseText  );
      $order->add_order_note( $message );
      $this->log( $message );
      $order->update_status( 'failed',
      __( 'Failed', 'woocommerce-trxservices' ) );
      return;
    }

    // Store GUID as transaction ID.
    add_post_meta( $order->get_id(), '_transaction_id', $guid, true );

    // Store transaction type.
    update_post_meta( $order->get_id(), '_trxservices_transaction_type', 'Credit ' . $this->mode );

    // Add order note.
    $message = $this->mode == 'Sale' ? 'TrxServices scheduled subscription payment approved (GUID: %s)' : 'TrxServices scheduled subscription payment authorization approved (GUID: %s)';
    $order->add_order_note( sprintf( __( $message, 'woocommerce-trxservices' ), $guid ) );
    $this->log( sprintf( __( $message, 'woocommerce-trxservices' ), $guid ) );

    if ($this->mode == 'Sale') {
      $order->payment_complete();
    }
    else {
      $order->update_status( 'on-hold',
      __( 'On-Hold', 'woocommerce-trxservices' ) );
    }
  }

  /**
   * Update the Account and StorageSafe tokens on a subscription where payment failed and a successful payment was made.
   *
   * @access public
   * @param WC_Subscription $subscription The subscription related to the failed payment method.
   * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
   * @return void
   */
  public function update_failing_payment_method( $subscription, $renewal_order ) {
    $subscription->update_meta_data( '_trxservices_account_token', $renewal_order->get_meta( '_trxservices_account_token', true ) );
    $subscription->update_meta_data( '_trxservices_storage_safe_token', $renewal_order->get_meta( '_trxservices_storage_safe_token', true ) );
  }

  /**
   * Add payment method details to My Subscriptions.
   *
   * @param string $payment_method_to_display
   * @param WC_Subscription $subscription
   *
   */
  public function add_subscription_payment_method($payment_method_to_display, $subscription ) {
    if ( $this->id === $subscription->get_payment_method() ) {
      $order_id = $subscription->get_parent_id();
      $order = wc_get_order( $order_id );
      $token_id = $order->get_meta( '_trxservices_token_id', true );
      if ($token_id) {
        $token = WC_Payment_Tokens::get( $token_id );
        $payment_method_to_display = sprintf( __( 'Via %1$s ending in %2$s', 'woocommerce-trxservices' ), $token->get_card_type(), $token->get_last4() );
      }
    }
    return $payment_method_to_display;
  }

  /**
   * Include the payment meta data required to process automatic recurring payments so that store managers can manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
   *
   * @param array $payment_meta associative array of meta data required for automatic payments
   * @param WC_Subscription $subscription An instance of a subscription object
   * @return array
   */
  public function add_subscription_payment_meta( $payment_meta, $subscription ) {
    $payment_meta[ $this->id ] = array(
      'post_meta' => array(
        '_trxservices_storage_safe_token' => array(
          'value' => get_post_meta( $subscription->id, '_trxservices_storage_safe_token', true ),
          'label' => 'TrxServices StorageSafe Wallet token',
        ),
      ),
    );
    return $payment_meta;
  }

}
