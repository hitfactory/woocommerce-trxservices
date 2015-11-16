<h3><?php _e( 'TrxServices', 'woocommerce-trxservices' ); ?></h3>

<div class="gateway-banner updated">
  <img src="<?php echo WC_TrxServices()->plugin_url() . '/assets/images/logo.png'; ?>" />
  <p class="main"><strong><?php _e( 'Getting started', 'woocommerce-trxservices' ); ?></strong></p>
  <p><?php _e( 'TrxServices is an advanced provider of debit card and credit card acceptance in the U.S. dedicated exclusively to the small-to-medium merchant market.', 'woocommerce-trxservices' ); ?></p>

  <?php if( empty( $this->algorithm ) ): ?>
  <p><a href="https://www.trxservices.com" target="_blank" class="button button-primary"><?php _e( 'Sign up for TrxServices', 'woocommerce-trxservices' ); ?></a>
  <a href="https://www.trxservices.com" target="_blank" class="button"><?php _e( 'Learn more', 'woocommerce-trxservices' ); ?></a></p>
  <?php endif; ?>
</div>

<table class="form-table">
  <?php $this->generate_settings_html(); ?>
</table>
