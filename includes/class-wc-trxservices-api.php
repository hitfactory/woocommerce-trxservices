<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * WC_TrxServices_API class.
 *
 * Communicates with Transaction Services.
 */
class WC_TrxServices_API {

  /**
   * API endpoint
   * @var string
   */
  private static $api_endpoint = 'https://api.trxservices.com/';

  /**
   * API credentials
   * @var array
   */
  private static $credentials = array();

  /**
   * Set API endpoint
   *
   * @param array $credentials
   */
  public static function set_api_endpoint( $api_endpoint ) {
    self::$api_endpoint = $api_endpoint;
  }


  /**
   * Get API credentials
   * @return array
   */
  public static function get_credentials() {
    if ( ! self::$credentials ) {
      $options = get_option( 'woocommerce_trxservices_settings' );
      switch($options['sandbox']) {
        case 'yes':
          $algorithm = $options['sandbox_algorithm'];
          $algorithm_mode = $options['sandbox_algorithm_mode'];
          $algorithm_key = $options['sandbox_algorithm_key'];
          $algorithm_iv = $options['sandbox_algorithm_iv'];
          $client = $options['sandbox_client'];
          $source = $options['sandbox_source'];
          self::set_api_endpoint('https://api.trxservices.net/');
          break;
        case 'no':
          $algorithm = $options['algorithm'];
          $algorithm_mode = $options['algorithm_mode'];
          $algorithm_key = $options['algorithm_key'];
          $algorithm_iv = $options['algorithm_iv'];
          $client = $options['client'];
          $source = $options['source'];
          break;
      }
      if ( $algorithm_key) {
        $algorithm_key  = self::hex2bin($algorithm_key);
      }
      if ( $algorithm_iv) {
        $algorithm_iv  = self::hex2bin($algorithm_iv);
      }
      $credentials = compact(
        'algorithm_key',
        'algorithm_iv',
        'client',
        'source'
      );
      self::set_credentials($credentials);
    }
    return self::$credentials;
  }

  /**
   * Set API credentials
   *
   * @param array $credentials
   */
  public static function set_credentials( $credentials ) {
    self::$credentials = $credentials;
  }

  /**
   * Decodes a hexadecimally encoded binary string
   *
   * @param  string $str Hexadecimally encoded binary string
   * @return string $bin Binary string
   */
  public static function hex2bin($str) {
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
  public static function encrypt($value) {
    $AES  = new AES_Encryption(self::$credentials['algorithm_key'], self::$credentials['algorithm_iv']);
    $encrypted = $AES->encrypt($value);
    return base64_encode($encrypted);
  }

  /**
   * Decrypt a value encyrpted using AES 256 encryption in CBC mode.
   *
   * @param  string $value Value to be decrypted
   * @return string Decrypted value.
   */
  public static function decrypt($value) {
    self::get_credentials();
    $AES  = new AES_Encryption(self::$credentials['algorithm_key'], self::$credentials['algorithm_iv']);
    return $AES->decrypt(base64_decode($value));
  }

  /**
   * Build XMLRequest string
   *
   * @param  array $data
   * @return string $xmlRequest
   */
  public static function build_request($data) {
    $xml = '';
    foreach($data as $child => $elements) {
      $xml .= "<$child>";
      foreach($elements as $key => $value) {
        $xml .= "<$key>" . htmlspecialchars(trim($value)) . "</$key>";
      }
      $xml .= "</$child>";
    }

    $message = 'TrxServices Request: ' . print_r( $xml, true ) . ')';
    self::log( $message );

    // Encrypt Request.
    $Request =  self::encrypt($xml);

    $xmlRequest  = '<Message>';
    $xmlRequest .= '<Request>'. $Request .'</Request>';
    $xmlRequest .= '<Authentication>';
    $xmlRequest .= '<Client>' . self::$credentials['client'] . '</Client>';
    $xmlRequest .= '<Source>' . self::$credentials['source'] . '</Source>';
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
  public static function do_request($data) {
    self::get_credentials();
    $xmlRequest = self::build_request($data);
    try {
      $response = wp_remote_post(self::$api_endpoint, array(
        'method' => 'POST',
        'body' => $xmlRequest,
        'timeout' => 15,
        'headers' => array('Content-Type' => 'text/xml'),
      ));

      if (is_wp_error($response)) {
        self::log( 'WP_Error: ' . print_r($response, TRUE) );
        throw new Exception(__('Unable to connect to TrxServices. Please try again.', 'woocommerce-trxservices'));
      }
    }
    catch (Exception $e) {
      $message = sprintf( __( '%s',
        'woocommerce-trxservices' ), $e->getMessage()  );
      self::log( $message );
      wc_add_notice( $message, 'error' );
      return;
    }

    // Process the XML response.
    $result = self::process_response($response);
    return $result;
  }

  /**
   * Process response body
   *
   * @param  array $response
   * @return array $result
   */
  public static function process_response($response) {
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
      $response = self::decrypt($responseString);

      $message = 'TrxServices Response: ' . print_r($response, true);
      self::log( $message );

      // Parse the decoded XML response.
      $response = "<Message>$response</Message>";
      $xmlResponse = simplexml_load_string($response);
      $guid = (string) $xmlResponse->Reference->Guid[0];
      $responseCode = (string) $xmlResponse->Result->ResponseCode;
      $responseText = (string) $xmlResponse->Result->ResponseText;
      if (isset($xmlResponse->Result->DebugInfo)) {
        $debugInfo = (string) $xmlResponse->Result->DebugInfo;
      }
      if (isset($xmlResponse->StorageSafe->Guid)) {
        $storage_safe_token = (string) $xmlResponse->StorageSafe->Guid;
      }
      if (isset($xmlResponse->Account->Guid)) {
        $account_token = (string) $xmlResponse->Account->Guid;
      }
      if (isset($xmlResponse->Shipping->Guid)) {
        $shipping_token = (string) $xmlResponse->Shipping->Guid;
      }
    }
    $result = compact(
      'guid',
      'responseCode',
      'responseText',
      'debugInfo',
      'storage_safe_token',
      'account_token',
      'shipping_token'
    );
    return $result;
  }

  /**
   * Callback for handling credit card.
   *
   * @param  WP_REST_Request $request
   * @return string $token StorageSafe token.
   */
  public static function handle_card( WP_REST_Request $request ) {
    $parameters = $request->get_params();
    if ($storage_safe_token = self::storage_safe_insert($parameters['card'])) {
      return rest_ensure_response($storage_safe_token);
    }
    $response = new WP_Error( 'trxservices_invalid_card', 'Unable to process credit card', array( 'status' => 422 ) );
    return rest_ensure_response( $response );
  }

  /**
   * Create a StorageSafe Wallet
   *
   * @param  array $card Credit card
   * @return string $storage_safe_token  StorageSafe token
   */
  public static function storage_safe_insert( $card ) {
    $expMonth = str_pad( $card['expMonth'], 2, '0', STR_PAD_LEFT );
    $expiration = $expMonth . $card['expYear'];
    $data = array(
      'Detail' => array(
        'TranType' => 'StorageSafe',
        'TranAction' => 'Insert',
        'Ip' => WC_Geolocation::get_ip_address(),
      ),
      'Account' => array(
        'Pan' => $card['number'],
        'Expiration' => $expiration,
      ),
    );
    $result = self::do_request($data);
    // Bail if request timed out.
    if (!$result) {
      return FALSE;
    }
    extract($result);
    switch ($responseCode) {
      case 'XD':
        $message = 'Returning token for existing TrxServices StorageSafe wallet (GUID: %s ResponseCode: %s ResponseText: %s)';
        $message = sprintf( __( $message, 'woocommerce-trxservices' ), $storage_safe_token, $responseCode, $responseText  );
        break;
      case '00':
        $message = 'TrxServices StorageSafe wallet created (GUID: %s)';
        $message = sprintf( __( $message, 'woocommerce-trxservices' ), $storage_safe_token  );
        break;
      default:
        $message = 'Unable to create TrxServices StorageSafe wallet (GUID: %s ResponseCode: %s ResponseText: %s)';
        $message = sprintf( __( $message, 'woocommerce-trxservices' ), $guid, $responseCode, $responseText  );
        self::log( $message );
        return FALSE;
    }
    self::log( $message );
    return $storage_safe_token;
  }

  /**
   * Log TrxServices API events
   *
   * @param string $message
   */
  public static function log( $message ) {
    $options = get_option( 'woocommerce_trxservices_settings' );
    if ( 'yes' === $options['debug'] ) {
      $logger = new WC_Logger();
      $logger->add( 'woocommerce-trxservices', $message );
    }
  }

}
