<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Settings for TrxServices Gateway
 */
return array(
  'enabled' => array(
    'title'       => __( 'Enable/Disable', 'woocommerce-trxservices' ),
    'label'       => __( 'Enable TrxServices', 'woocommerce-trxservices' ),
    'type'        => 'checkbox',
    'description' => '',
    'default'     => 'no'
  ),
  'title' => array(
    'title'       => __( 'Title', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-trxservices' ),
    'default'     => __( 'TrxServices', 'woocommerce-trxservices' ),
    'desc_tip'    => true
  ),
  'description' => array(
    'title'       => __( 'Description', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-trxservices' ),
    'default'     => 'Pay with TrxServices',
    'desc_tip'    => true
  ),
  'instructions' => array(
    'title'       => __( 'Instructions', 'woocommerce-trxservices' ),
    'type'        => 'textarea',
    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-trxservices' ),
    'default'     => '',
    'desc_tip'    => true,
  ),
  'debug' => array(
    'title'       => __( 'Debug Log', 'woocommerce-trxservices' ),
    'type'        => 'checkbox',
    'label'       => __( 'Enable logging', 'woocommerce-trxservices' ),
    'default'     => 'no',
    'description' => sprintf( __( 'Log TrxServices events inside <code>%s</code>', 'woocommerce-trxservices' ), wc_get_log_file_path( $this->id ) )
  ),
  'sandbox' => array(
    'title'       => __( 'Sandbox', 'woocommerce-trxservices' ),
    'label'       => __( 'Enable Sandbox Mode', 'woocommerce-trxservices' ),
    'type'        => 'checkbox',
    'description' => __( 'Place the payment gateway in sandbox mode (real payments will not be taken).', 'woocommerce-trxservices' ),
    'default'     => 'yes'
  ),
);
