"use strict";
(function ($, woocommerce_admin) {
  
  $( function() {
    $('.anyday-payment-action').on('click', function(e) {
      var action = $(this).data('anydayAction'),
          data = {
            action: action,
            orderId: $(this).attr('data-order-id'),
          };

      if(action === 'adm_capture_payment') {
        var amount = prompt(anyday.capturePrompt);

        if(amount === null) return;

        amount = validateAmount(amount);

        if(!amount) {
          alert(anyday.capturePromptValidation);
          return;
        }

        data.amount = amount;
      } else if (action === 'adm_cancel_payment') {
        var shouldCancel = confirm(anyday.cancelConfirmation);

        if(!shouldCancel) return;
      } else if (action === 'adm_refund_payment') {
        var amount = prompt(anyday.refundConfirmation);

        if(amount === null) return;

        amount = validateAmount(amount);

        if(!amount) {
          alert(anyday.capturePromptValidation);
          return;
        }

        data.amount = amount;
      }

      $.ajax({
        type: "POST",
        dataType: "JSON",
        url: anyday.ajaxUrl,
        data,
        success: function(response){
          if (response.success) {
            if(!alert(response.success)){window.location.reload(true);}
          } else if (response.error) {
            if(!alert(response.error)){window.location.reload(true);}
          }
        }
      });
    });

    function validateAmount(amount) {
      let amtPattern = /^((?:\d{1,3}((?:[\s,]\d{3})+|(?:,\d{0,2}))(?:\.\d{0,2})?)$|^(?:\d{1,3}((?:[\s.]\d{3})+|(?:\.\d{0,2}))(?:\,\d{0,2})?)$|(^\d+[,\.]?)$|(?:\d+[,\.]\d{0,2})$)/;
      var dotPattern = [];
      var zeroReg    = /^0+$/;
      var amt        = false;
      var dec, decLength;
      if ((amount.charAt(0) == '.' || amount.charAt(0) == ',')) {
        dotPattern = amount.match(/[,|\.]/g);
        if (dotPattern.length == 1) {
          amount = "0" + amount;
        }
      }
      if(amtPattern.test(amount)) {
        dotPattern = amount.match(/[,|\.]/g);
        amt = amount.match(/\d+/g).join('');
        if(zeroReg.test(amt))
          return false;
        if(dotPattern && dotPattern.length > 0) {
          dec = dotPattern[dotPattern.length - 1];
          decLength = amount.split(dec)[1].length;
          if ((amount.split(dec)[0] == "0" && dotPattern.length != 1) || decLength <= 2 ) {
            amt = amt.substring(0, amt.length - decLength) + "." + amt.substring(amt.length - decLength);
          }
        } else {
          return amt;
        }
      }
      return amt;
    }

    $( '#mainform' ).on( 'click', '.wc-payment-gateway-method-toggle-enabled', function() {
      var $link   = $( this ),
        $toggle = $link.find( '.woocommerce-input-toggle' );

      var data = {
        action: 'woocommerce_toggle_gateway_enabled',
        security: woocommerce_admin.nonces.gateway_toggle,
        gateway_id: 'anyday_payment_gateway'
      };

      $toggle.addClass( 'woocommerce-input-toggle--loading' );

      $.ajax( {
        url:      woocommerce_admin.ajax_url,
        data:     data,
        dataType : 'json',
        type     : 'POST',
        success:  function( response ) {
          if ( true === response.data ) {
            $toggle.removeClass( 'woocommerce-input-toggle--enabled, woocommerce-input-toggle--disabled' );
            $toggle.addClass( 'woocommerce-input-toggle--enabled' );
            $toggle.removeClass( 'woocommerce-input-toggle--loading' );
          } else if ( false === response.data ) {
            $toggle.removeClass( 'woocommerce-input-toggle--enabled, woocommerce-input-toggle--disabled' );
            $toggle.addClass( 'woocommerce-input-toggle--disabled' );
            $toggle.removeClass( 'woocommerce-input-toggle--loading' );
          } else if ( 'needs_setup' === response.data ) {
            window.location.href = $link.attr( 'href' );
          }
        }
      } );

      return false;
    });
  });
  
})( jQuery, woocommerce_admin );