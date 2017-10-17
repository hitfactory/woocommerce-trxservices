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
  'sandbox_credentials' => array(
    'title'       => __( 'Sandbox Credentials', 'woocommerce-trxservices' ),
    'type'        => 'title',
    'description' => '',
  ),
  'sandbox_algorithm' => array(
    'title'       => __( 'Sandbox Algorithm', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'sandbox_algorithm_mode' => array(
    'title'       => __( 'Sandbox Algorithm Mode', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'sandbox_algorithm_key' => array(
    'title'       => __( 'Sandbox Algorithm Key', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'sandbox_algorithm_iv' => array(
    'title'       => __( 'Sandbox Algorithm IV', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'sandbox_client' => array(
    'title'       => __( 'Sandbox Client', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'sandbox_source' => array(
    'title'       => __( 'Sandbox Source', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
   'credentials' => array(
    'title'       => __( 'Production Credentials', 'woocommerce-trxservices' ),
    'type'        => 'title',
    'description' => '',
  ),
  'algorithm' => array(
    'title'       => __( 'Production Algorithm', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'algorithm_mode' => array(
    'title'       => __( 'Production Algorithm Mode', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'algorithm_key' => array(
    'title'       => __( 'Production Algorithm Key', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'algorithm_iv' => array(
    'title'       => __( 'Production Algorithm IV', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'client' => array(
    'title'       => __( 'Production Client', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'source' => array(
    'title'       => __( 'Production Source', 'woocommerce-trxservices' ),
    'type'        => 'text',
    'default'     => '',
  ),
  'mode' => array(
    'title'       => __( 'Mode', 'woocommerce' ),
    'type'        => 'select',
    'class'       => 'wc-enhanced-select',
    'description' => __( 'Credit Sale performs a credit authorization and captures it for settlement in one request. Credit Auth only requests a credit authorization.', 'woocommerce' ),
    'default'     => 'creditsale',
    'desc_tip'    => true,
    'options'     => array(
      'creditsale' => __( 'Credit Sale', 'woocommerce-trxservices' ),
      'creditauth' => __( 'Credit Auth', 'woocommerce-trxservices' )
    )
  ),
);
