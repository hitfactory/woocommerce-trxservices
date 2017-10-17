== Description ==

Accept payments in US dollars from major credit cards on your WooCommerce website with Transaction Services (TrxServices).

The plugin currently supports the following types of transactions:

* Credit Sale
* Credit Return
* Credit Auth
* Credit Void
* Credit Capture
* StorageSafe Insert

The plugin introduces two custom order actions for further processing of Credit Auth transactions.

* Capture payment
* Void payment

Supports automatic refunds.

A refund for a Credit Sale transaction is processed as a Credit Return.
A refund for a Credit Auth transaction is processed as a Credit Void.

Supports subscriptions.



== Installation ==

Please note, version 1.1.0 of this gateway requires WooCommerce 3.0 and above.

1. Upload the `woocommerce-trxservices` folder to your plugins directory (e.g. `/wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Sign up for a Transaction Services account.
4. Add your test/production credentials from the WooCommerce Checkout settings page.
5. Enable the payment gateway from the WooCommerce Checkout settings page.
6. Choose the Mode of transactions you wish to support: Credit Sale or Credit Auth.

