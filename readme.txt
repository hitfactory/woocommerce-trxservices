== Description ==

Accept payments in US dollars from major credit cards on your WooCommerce website with Transaction Services (TrxServices).

The plugin currently supports the following types of transactions: 

* Credit Sale
* Credit Return
* Credit Auth
* Credit Void
* Credit Capture

The plugin introduces two custom order actions for further processing of Credit Auth transactions.

* Capture payment
* Void payment

Supports automatic refunds.

A refund for a Credit Sale transaction is processed as a Credit Return.
A refund for a Credit Auth transaction is processed as a Credit Void.

== Installation ==

1. Upload the `woocommerce-trxservices` folder to your plugins directory (e.g. `/wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Sign up for a TrxServices.net account.
4. Add your test/production credentials to wp-config.php.

/**
 * TrxServices test credentials.
 */
define( 'TRXSERVICES_SANDBOX_ALGORITHM',          'Rijndael' );
define( 'TRXSERVICES_SANDBOX_ALGORITHM_MODE',     'CBC' );
define( 'TRXSERVICES_SANDBOX_ALGORITHM_KEY',      'YOUR-ALGORITHM-KEY' );
define( 'TRXSERVICES_SANDBOX_ALGORITHM_IV',       'YOUR-ALGORITHM-IV' );
define( 'TRXSERVICES_SANDBOX_CLIENT',             'YOUR-CLIENT-ID' );
define( 'TRXSERVICES_SANDBOX_SOURCE',             'YOUR-SOURCE' );

/**
 * TrxServices production credentials.
 */
define( 'TRXSERVICES_ALGORITHM',          'Rijndael' );
define( 'TRXSERVICES_ALGORITHM_MODE',     'CBC' );
define( 'TRXSERVICES_ALGORITHM_KEY',      'YOUR-ALGORITHM-KEY' );
define( 'TRXSERVICES_ALGORITHM_IV',       'YOUR-ALGORITHM-IV' );
define( 'TRXSERVICES_CLIENT',             'YOUR-CLIENT-ID' );
define( 'TRXSERVICES_SOURCE',             'YOUR-SOURCE' );

5. Enable the payment gateway from the WooCommerce Checkout settings page.
6. Choose the Mode of transactions you wish to support: Credit Sale or Credit Auth.

Credit Sale performs a credit authorization and captures it for settlement in one request. 
Credit Auth only requests a credit authorization.

