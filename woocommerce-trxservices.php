<?php
/*
 * Plugin Name:       WooCommerce TrxServices
 * Description:       TrxServices payment gateway for WooCommerce.
 * Version:           1.0.0
 * Author:            The Hit Factory
 * Author URI:        http://hitfactory.co.nz
 * Text Domain:       woocommerce-trxservices
 * Domain Path:       /languages
 * License:           GPLv2 or later
 *
 * WooCommerce TrxServices is distributed under the terms of the 
 * GNU General Public License as published by the Free Software Foundation, 
 * either version 2 of the License, or any later version.
 *
 * WooCommerce TrxServices is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce TrxServices. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package  WC_TrxServices
 * @author   The Hit Factory
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Required functions
 */
require_once('woo-includes/woo-functions.php');

if ( !class_exists( 'WC_TrxServices' ) ) {

  /**
   * WooCommerce TrxServices main class.
   *
   * @class   WC_TrxServices
   * @version 1.0.0
   */
  final class WC_TrxServices {

    /**
     * Instance of this class.
     *
     * @access protected
     * @access static
     * @var object
     */
    protected static $instance = null;

    /**
     * Slug
     *
     * @access public
     * @var    string
     */
     public $gateway_slug = 'trxservices';

    /**
     * Text Domain
     *
     * @access public
     * @var    string
     */
    public $text_domain = 'woocommerce-trxservices';

    /**
     * The Gateway Name.
     *
     * @access public
     * @var    string
     */
     public $name = "TrxServices";

    /**
     * Gateway version.
     *
     * @access public
     * @var    string
     */
    public $version = '1.0.0';

    /**
     * The Gateway URL.
     *
     * @access public
     * @var    string
     */
     public $web_url = "https://www.trxservices.com";

    /**
     * The Gateway documentation URL.
     *
     * @access public
     * @var    string
     */
     public $doc_url = "";

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance() {
      // If the single instance hasn't been set, set it now.
      if ( null == self::$instance ) {
        self::$instance = new self;
      }

      return self::$instance;
    }

    /**
     * Throw error on object clone
     *
     * The whole idea of the singleton design pattern is that there is a single
     * object therefore, we don't want the object to be cloned.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
     public function __clone() {
       // Cloning instances of the class is forbidden
       _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-trxservices' ), $this->version );
     }

    /**
     * Disable unserializing of the class
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
     public function __wakeup() {
       // Unserializing instances of the class is forbidden
       _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-trxservices' ), $this->version );
     }

    /**
     * Initialize the plugin public actions.
     *
     * @access private
     */
    private function __construct() {
      // Hooks.
      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
      add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
      add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

      // Is WooCommerce activated?
      if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        add_action('admin_notices', array( $this, 'woocommerce_missing_notice' ) );
        return false;
      }
      else{
        // Check we have the minimum version of WooCommerce required before loading the gateway.
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
          if ( class_exists( 'WC_Payment_Gateway' ) ) {

            $this->includes();

            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
            add_filter( 'woocommerce_currencies', array( $this, 'add_currency' ) );
            add_filter( 'woocommerce_currency_symbol', array( $this, 'add_currency_symbol' ), 10, 2 );
          }
        }
        else {
          add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
          return false;
        }
      }
    }

    /**
     * Plugin action links.
     *
     * @access public
     * @param  mixed $links
     * @return void
     */
     public function action_links( $links ) {
       if ( current_user_can( 'manage_woocommerce' ) ) {
         $plugin_links = array(
           '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_' . $this->gateway_slug ) . '">' . __( 'Payment Settings', 'woocommerce-trxservices' ) . '</a>',
         );
         return array_merge( $plugin_links, $links );
       }

       return $links;
     }

    /**
     * Plugin row meta links
     *
     * @access public
     * @param  array $input already defined meta links
     * @param  string $file plugin file path and name being processed
     * @return array $input
     */
     public function plugin_row_meta( $input, $file ) {
       if ( plugin_basename( __FILE__ ) !== $file ) {
         return $input;
       }

       $links = array(
         '<a href="' . esc_url( $this->doc_url ) . '">' . __( 'Documentation', 'woocommerce-trxservices' ) . '</a>',
       );

       $input = array_merge( $input, $links );

       return $input;
     }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any 
     * following ones if the same translation is present.
     *
     * @access public
     * @return void
     */
    public function load_plugin_textdomain() {
      // Set filter for plugin's languages directory
      $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
      $lang_dir = apply_filters( 'woocommerce_' . $this->gateway_slug . '_languages_directory', $lang_dir );

      // Traditional WordPress plugin locale filter
      $locale = apply_filters( 'plugin_locale',  get_locale(), $this->text_domain );
      $mofile = sprintf( '%1$s-%2$s.mo', $this->text_domain, $locale );

      // Setup paths to current locale file
      $mofile_local  = $lang_dir . $mofile;
      $mofile_global = WP_LANG_DIR . '/' . $this->text_domain . '/' . $mofile;

      if ( file_exists( $mofile_global ) ) {
        load_textdomain( $this->text_domain, $mofile_global );
      }
      else if ( file_exists( $mofile_local ) ) {
        load_textdomain( $this->text_domain, $mofile_local );
      }
      else {
        // Load the default language files
        load_plugin_textdomain( $this->text_domain, false, $lang_dir );
      }
    }

    /**
     * Include files.
     *
     * @access private
     * @return void
     */
    private function includes() {

      // @TODO: Switch to https://github.com/defuse/php-encryption.
      // Helper encryption classes. 
      include_once( 'includes/lib/AES.php' );
      include_once( 'includes/lib/AES_Encryption.php' );
      include_once( 'includes/lib/padCrypt.php' );
      
      include_once( 'includes/class-wc-gateway-' . str_replace( '_', '-', $this->gateway_slug ) . '.php' );
    }

    /**
     * This filters the gateway to only supported countries.
     *
     * @access public
     */
    public function gateway_country_base() {
      return apply_filters( 'woocommerce_trxservices_gateway_country_base', array( 'US' ) );
    }

    /**
     * Add the gateway.
     *
     * @access public
     * @param  array $methods WooCommerce payment methods.
     * @return array WooCommerce TrxServices gateway.
     */
    public function add_gateway( $methods ) {
      // This checks if the gateway is supported for your country.
      if ( in_array( WC()->countries->get_base_country(), $this->gateway_country_base() ) ) {
        $methods[] = 'WC_Gateway_' . str_replace( ' ', '_', $this->name );
      }
      return $methods;
    }

    /**
     * Add the currency.
     *
     * @access public
     * @return array
     */
    public function add_currency( $currencies ) {
      return $currencies;
    }

    /**
     * Add the currency symbol.
     *
     * @access public
     * @return string
     */
    public function add_currency_symbol( $currency_symbol, $currency ) {
      return $currency_symbol;
    }

    /**
     * WooCommerce Fallback Notice.
     *
     * @access public
     * @return string
     */
    public function woocommerce_missing_notice() {
      echo '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Sorry, <strong>WooCommerce %s</strong> requires WooCommerce to be installed and activated first. Please install <a href="%s">WooCommerce</a> first.', $this->text_domain), $this->name, admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce' ) ) . '</p></div>';
    }

    /**
     * WooCommerce TrxServices Upgrade Notice.
     *
     * @access public
     * @return string
     */
    public function upgrade_notice() {
      echo '<div class="updated woocommerce-message wc-connect"><p>' . sprintf( __( 'WooCommerce %s depends on version 2.2 and up of WooCommerce for this gateway to work! Please upgrade before activating.', 'woocommerce-trxservices' ), $this->name ) . '</p></div>';
    }

    /** Helper functions ******************************************************/

    /**
     * Get the plugin url.
     *
     * @access public
     * @return string
     */
    public function plugin_url() {
      return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Get the plugin path.
     *
     * @access public
     * @return string
     */
    public function plugin_path() {
      return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

  } // end class

  add_action( 'plugins_loaded', array( 'WC_TrxServices', 'get_instance' ), 0 );
  
  // Here because these actions don't get loaded from the payment gateway class.
  add_action( 'woocommerce_order_actions', 'add_order_actions' );
  add_action( 'woocommerce_order_action_trxservices_creditcapture', 'woocommerce_trxservices_creditcapture');
  add_action( 'woocommerce_order_action_trxservices_creditvoid', 'woocommerce_trxservices_creditvoid');
} // end if class exists.

/**
 * Returns the main instance of WC_TrxServices to prevent the need to use globals.
 *
 * @return WC_TrxServices
 */
function wc_trxservices() {
	return WC_TrxServices::get_instance();
}

/**
 * Returns the main instance of WC_Gateway_TrxServices to prevent the need to use globals.
 *
 * @return WC_Gateway_TrxServices
 */
function wc_gateway_trxservices() {
  include_once( 'includes/class-wc-gateway-trxservices.php' );
  return WC_Gateway_TrxServices::get_instance();
}

 /**
 * Add custom order actions.
 */
function add_order_actions($actions) {
  global $theorder;
  switch ($theorder->status) {
    case 'on-hold':
      $actions['trxservices_creditcapture'] = __( 'Capture payment', 'woocommerce-trxservices' );
      break;
    case 'processing':
      $actions['trxservices_creditvoid'] = __( 'Void payment', 'woocommerce-trxservices' );
      break;  
  }
  return $actions;
}

/**
 * Perform a Credit Capture transaction.
 * 
 * @param  WC_Order $order WooCommerce order
 * @return bool     
 */
function woocommerce_trxservices_creditcapture($order) {
  $trxservices = wc_gateway_trxservices();
  $trxservices->process_creditcapture($order);
}

 /**
 * Perform a Credit Void transaction.
 * 
 * @param  WC_Order $order WooCommerce order
 * @return bool     
 */
function woocommerce_trxservices_creditvoid($order) {
  $trxservices = wc_gateway_trxservices();
  $trxservices->process_creditvoid($order);
}
