(function($) {
  var TrxServices = {
    handleCard: function() {
      var paymentForm = $( 'form.checkout, form#order_review, form#add_payment_method' ),
          creditCardForm =  $( '#wc-trxservices-cc-form' );
      if ( ( $( '#payment_method_trxservices' ).is( ':checked' ) && 'new' === $( 'input[name="wc-trxservices-payment-token"]:checked' ).val() ) || ( '1' === $( '#woocommerce_add_payment_method' ).val() ) ) {

        if ( 0 === $( 'input.trxservices-token' ).length ) {
          paymentForm.block({
            message: null,
            overlayCSS: {
              background: '#fff',
              opacity: 0.6
            }
          });

          var card         = $( '#trxservices-card-number' ).val(),
            cvc            = $( '#trxservices-card-cvc' ).val(),
            expiry         = $.payment.cardExpiryVal( $( '#trxservices-card-expiry' ).val() ),
            address1       = paymentForm.find( '#billing_address_1' ).val() || '',
            address2       = paymentForm.find( '#billing_address_2' ).val() || '',
            addressCountry = paymentForm.find( '#billing_country' ).val() || '',
            addressState   = paymentForm.find( '#billing_state' ).val() || '',
            addressCity    = paymentForm.find( '#billing_city' ).val() || '',
            addressZip     = paymentForm.find( '#billing_postcode' ).val() || '';

          addressZip = addressZip.replace( /-/g, '' );
          card = card.replace( /\s/g, '' );
          $.post('/wp-json/wc-trxservices/v1/card', {
            card: {
              number: card,
              cvc: cvc,
              expMonth: expiry.month,
              expYear: ( expiry.year - 2000 ),
              addressLine1: address1,
              addressLine2: address2,
              addressCountry: addressCountry,
              addressState: addressState,
              addressZip: addressZip,
              addressCity: addressCity
            }
          }, function(token) {
            var cardType = $.payment.cardType(card);
            var lastFour = card.substr(card.length - 4);
            creditCardForm
              .append( '<input type="hidden" class="trxservices-token" name="trxservices_token" value="' + token + '"/>' )
              .append( '<input type="hidden" class="trxservices-card-type" name="trxservices_card_type" value="' + cardType + '"/>' )
              .append( '<input type="hidden" class="trxservices-last-four" name="trxservices_last_four" value="' + lastFour + '"/>' )
              .append( '<input type="hidden" class="trxservices-expiry-month" name="trxservices_expiry_month" value="' + expiry.month + '"/>' )
              .append( '<input type="hidden" class="trxservices-expiry-year" name="trxservices_expiry_year" value="' + expiry.year + '"/>' );
            paymentForm.submit();
          })
          .fail(function(jqXHR, textStatus, error) {
            $( '.woocommerce-error, .trxservices-token', creditCardForm ).remove();
            paymentForm.unblock();
            var errorMessage = 'Sorry, we were unable to process this credit card.';
            creditCardForm.prepend( '<ul class="woocommerce-error"><li>' + errorMessage + '</li></ul>' );
          });

          // Prevent the form from submitting
          return false;
        }
      }
      return true;
    },
    removeInputs: function() {
      $( '.trxservices-token' ).remove();
      $( '.trxservices-last-four' ).remove();
      $( '.trxservices-card-type' ).remove();
      $( '.trxservices-expiry-month' ).remove();
      $( '.trxservices-expiry-year' ).remove();
    }
  }

  $( document.body ).on( 'checkout_error', function () {
    TrxServices.removeInputs();
  });

  /* Checkout Form */
  $( 'form.checkout' ).on( 'checkout_place_order_trxservices', function () {
    return TrxServices.handleCard();
  });

  /* Order review form */
  $( 'form#order_review' ).on( 'submit', function () {
    return TrxServices.handleCard();
  });

  /* Add payment method form */
  $( 'form#add_payment_method' ).on( 'submit', function () {
    return TrxServices.handleCard();
  });

  /* All forms */
  $( 'form.checkout, form#order_review, form#add_payment_method' )
  .on( 'change', '#wc-trxservices-cc-form input', function() {
    TrxServices.removeInputs();
  });

}( jQuery ));
