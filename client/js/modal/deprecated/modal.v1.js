let taptreeModalHelper = null;

class ElementHelper {
  constructor(element) {
    this.el = element;
  }

  setText(text) {
    this.el.innerText = text;
    return this.el;
  }

  appendHTML(...htmlStrings) {
    for (const htmlString of htmlStrings) {
      this.el.insertAdjacentHTML('beforeend', htmlString);
    }
    return this.el;
  }

  on(eventType, ...handlers) {
    for (const handler of handlers) {
      this.el.addEventListener(eventType, handler);
    }
    return this.el;
  }

  removeOn(eventType, ...handlers) {
    for (const handler of handlers) {
      this.el.removeEventListener(eventType, handler);
    }
    return this.el;
  }

  is(selectors) {
    return this.el.matches(selectors);
  }
}

const Q = (selector) => {
  if (document[selector]) {
    return new ElementHelper(document[selector]);
  }
  return new ElementHelper(document.querySelector(selector));
};

jQuery(function ($) {
  if (typeof wc_checkout_params === 'undefined') {
    return false;
  }

  console.log('wc_checkout_params:', wc_checkout_params);
  taptreeModalHelper = {
    modal: null,
    modalInterval: null,
    modalTimeout: null,
    orderPayUrl: null,
    thankYouUrl: null,
    blocker:
      '<div id="taptree-blocker" style="z-index: 1001; position: fixed; height: 100%; width: 100%; top: 0; left: 0; background-color: #000; opacity: 0.75;"><div id="taptree-blocker-textbox" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);"><p id="taptree-blocker-text" style="max-width: 350px; text-align: center; color: #fff">Wir öffnen das sichere TapTree Payments Browserfenster für dich. Einen Moment bitte ...</p></div></div>',

    updateBlockerWhenModalReady: () => {
      const modalReadyBlockerText =
        'Du kannst das sichere TapTree Payments Browserfenster nicht sehen? Klicke hier um das Fenster anzuzeigen und deinen Kauf abzuschließen';
      Q('#taptree-blocker-text').setText(modalReadyBlockerText);

      const continueParagraph =
        '<p id="taptree-blocker-continue-text" style="text-align: center; color: #fff; font-weight: bold; text-decoration: underline;">Weiter</p>';
      Q('#taptree-blocker-textbox').appendHTML(continueParagraph);

      const blockerButton =
        '<button id="taptree-blocker-focus-button" style="position: fixed; height: 100%; width: 100%; top: 0; left: 0; cursor: pointer; background-color: transparent;" onClick="taptreeModalHelper.focusModal()"></button>';
      Q('#taptree-blocker').appendHTML(blockerButton);
    },

    modalHandler: () => {
      try {
        // If modal is closed without completing payment
        if (!taptreeModalHelper.checkModal()) {
          console.log('Redirecting to order pay from modalHandler');
          taptreeModalHelper.redirectToOrderPay();
          return;
        }

        const isModalOnThankYouPage =
          taptreeModalHelper.isModalOnThankYouPage();

        // If modal is still open but on our domain, let's inspect the path:
        if (
          taptreeModalHelper.modal.location.hostname ===
            window.location.hostname &&
          !isModalOnThankYouPage
        ) {
          // Anything else than thank you page, close modal and redirect
          taptreeModalHelper.closeModalAndRedirect();
        } else if (isModalOnThankYouPage) {
          // If modal is on the thank-you page, redirect and stop further actions
          console.log(
            'Modal is on the thank-you page. Redirecting to Thank You.'
          );
          taptreeModalHelper.clearTimers();
          taptreeModalHelper.showLoadingOverlay();
          taptreeModalHelper.closeModal();
          window.location = taptreeModalHelper.thankYouUrl;
        }
      } catch (err) {
        //console.error('Error in modalHandler:', err);
      }
    },

    closeModalAndRedirect: () => {
      if (
        taptreeModalHelper.modal.location.hostname === window.location.hostname
      ) {
        taptreeModalHelper.showLoadingOverlay();

        taptreeModalHelper.releaseUiAndCleanUp();
        taptreeModalHelper.closeModal();

        // If modal matches thank-you page, redirect and stop further actions
        if (taptreeModalHelper.isModalOnThankYouPage()) {
          console.log('Closing modal and redirecting to Thank You page.');

          window.location = taptreeModalHelper.thankYouUrl;
          return;
        } else {
          console.log('Closing modal and redirecting to order pay.');
          window.location = taptreeModalHelper.orderPayUrl;
        }
      }
    },

    redirectToOrderPay: () => {
      // If we don't have a stored orderPayUrl for some reason, fallback to the checkout page

      let target =
        taptreeModalHelper.orderPayUrl || wc_checkout_params.checkout_url;
      taptreeModalHelper.showLoadingOverlay(); // Show overlay while redirecting
      console.log('redirectToOrderPay: Redirecting to:', target);
      window.location = target;
    },

    showLoadingOverlay: () => {
      if (!document.querySelector('#taptree-blocker')) {
        $(taptreeModalHelper.blocker).appendTo('body');
      }

      Q('#taptree-blocker-text').setText(
        'Einen Moment bitte... Wir leiten dich weiter.'
      );

      // Only add the spinner if it doesn’t exist yet
      if (!document.querySelector('#taptree-spinner-container')) {
        Q('#taptree-blocker-textbox').appendHTML(`
          <div id="taptree-spinner-container" style="margin-top:20px; text-align:center;">
            <div style="display:inline-block; width:40px; height:40px; border:4px solid #fff; border-radius:50%; border-top-color:transparent; animation: spin 1s linear infinite;"></div>
            <style>@keyframes spin { to {transform: rotate(360deg);} }</style>
          </div>
        `);
      }
    },

    checkModal: () => {
      if (!taptreeModalHelper.modal || taptreeModalHelper.modal.closed) {
        taptreeModalHelper.clearTimers();
        taptreeModalHelper.showLoadingOverlay();
        console.log('Redirecting to order pay from checkModal');
        taptreeModalHelper.redirectToOrderPay();
        return false;
      }

      if (taptreeModalHelper.isModalOnThankYouPage()) {
        console.log('Modal is on the thank-you page. Stopping further checks.');
        taptreeModalHelper.clearTimers(); // Stop interval checks
        return true;
      }

      return true;
    },

    isModalOnThankYouPage: () => {
      try {
        if (!taptreeModalHelper.modal || taptreeModalHelper.modal.closed) {
          return false;
        }

        const modalPath = taptreeModalHelper.modal.location.pathname;
        const thankYouUrl = new URL(taptreeModalHelper.thankYouUrl);
        const thankYouPath = thankYouUrl.pathname;

        return modalPath === thankYouPath;
      } catch (err) {
        //console.error('Error in isModalOnThankYouPage:', err);
        return false;
      }
    },

    releaseUiAndCleanUp: () => {
      taptreeModalHelper.clearTimers();
      taptreeModalHelper.detachEvents();
      taptreeCheckoutFormOverrides.releaseUi();
    },

    setTimers: () => {
      taptreeModalHelper.modalTimeout = setTimeout(
        taptreeModalHelper.closeModal,
        15 * 60 * 1000 // 15 minutes timeout
      );
      taptreeModalHelper.modalInterval = setInterval(() => {
        taptreeModalHelper.modalHandler();
      }, 50);
    },

    clearTimers: () => {
      clearTimeout(taptreeModalHelper.modalTimeout);
      clearInterval(taptreeModalHelper.modalInterval);
    },

    closeModal: () => {
      if (taptreeModalHelper.modal && !taptreeModalHelper.modal.closed) {
        taptreeModalHelper.modal.close();
      }

      taptreeModalHelper.showLoadingOverlay();
    },

    modalFocused: null,

    setModalFocused: (val) => {
      if (typeof val === 'object' && val instanceof Event) {
        if (val.type === 'blur') {
          taptreeModalHelper.modalFocused = true;
        }
      } else if (typeof val === 'boolean') {
        taptreeModalHelper.modalFocused = val;
      }
    },

    alertIfModalNotFocused: () => {
      if (!taptreeModalHelper.modalFocused) {
        window.alert(
          'Bitte wechsle den Browsertab um die TapTree Payments Zahlung abzuschließen'
        );
      }
      window.removeEventListener('blur', taptreeModalHelper.setModalFocused);
    },

    focusModal: () => {
      taptreeModalHelper.setModalFocused(false);
      if (taptreeModalHelper.modal && !taptreeModalHelper.modal.closed) {
        window.addEventListener('blur', taptreeModalHelper.setModalFocused);
        taptreeModalHelper.modal.focus();
        setTimeout(taptreeModalHelper.alertIfModalNotFocused, 150);
      }
    },

    promptUnloadIfModal: (e) => {
      /*if (taptreeModalHelper.modal) {
        e.preventDefault();
        e.returnValue = '';
      }*/
    },

    attachUnloadEvents: () => {
      window.addEventListener(
        'beforeunload',
        taptreeModalHelper.promptUnloadIfModal
      );
      window.addEventListener('unload', taptreeModalHelper.closeModal);
    },

    detachEvents: () => {
      window.removeEventListener(
        'beforeunload',
        taptreeModalHelper.promptUnloadIfModal
      );
      window.removeEventListener('unload', taptreeModalHelper.closeModal);
      window.removeEventListener('blur', taptreeModalHelper.setModalFocused);
    },
  };

  const taptreeCheckoutFormOverrides = {
    $checkout_form: $('form.checkout, form#order_review'), // Target both checkout and order-pay forms

    blockOnSubmit: function ($form) {
      let isBlocked = $form.data('blockUI.isBlocked');

      if (1 !== isBlocked) {
        $form.block({
          message: null,
          overlayCSS: {
            background: '#fff',
            opacity: 0,
          },
        });
      }

      $(taptreeModalHelper.blocker).appendTo('body');
    },

    get_payment_method: function () {
      return taptreeCheckoutFormOverrides.$checkout_form
        .find('input[name="payment_method"]:checked')
        .val();
    },

    init: function () {
      this.$checkout_form.on('submit', this.submit);
    },

    releaseUi: function () {
      taptreeCheckoutFormOverrides.$checkout_form
        .removeClass('processing')
        .unblock();
      $('#taptree-blocker').remove();
    },

    submit_error: function (error_message) {
      $(
        '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
      ).remove();
      taptreeCheckoutFormOverrides.$checkout_form.prepend(
        '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
          error_message +
          '</div>'
      ); // eslint-disable-line max-len
      taptreeModalHelper.releaseUiAndCleanUp();
      taptreeCheckoutFormOverrides.$checkout_form
        .find('.input-text, select, input:checkbox')
        .trigger('validate')
        .trigger('blur');
      wc_checkout_form.scroll_to_notices();
      $(document.body).trigger('checkout_error', [error_message]);
    },

    submit: async function (e) {
      const paymentMethod = $('input[name="payment_method"]:checked').val();
      if (!paymentMethod.startsWith('taptree_wc_gateway_')) {
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      let $form = $(this);

      if ($form.is('.processing')) {
        return false;
      }

      // Trigger a handler to let gateways manipulate the checkout if needed
      if (
        $form.triggerHandler('checkout_place_order') !== false &&
        $form.triggerHandler(
          'checkout_place_order_' +
            taptreeCheckoutFormOverrides.get_payment_method()
        ) !== false
      ) {
        $form.addClass('processing');

        taptreeCheckoutFormOverrides.blockOnSubmit($form);

        taptreeModalHelper.attachUnloadEvents();

        // Ensure JSON is valid once returned.
        await $.ajaxSetup({
          dataFilter: function (raw_response, dataType) {
            // We only want to work with JSON
            if ('json' !== dataType) {
              return raw_response;
            }

            if (wc_checkout_form.is_valid_json(raw_response)) {
              return raw_response;
            } else {
              // Attempt to fix the malformed JSON
              let maybe_valid_json = raw_response.match(/{"result.*}/);

              if (null === maybe_valid_json) {
                // Unable to fix malformed JSON
              } else if (wc_checkout_form.is_valid_json(maybe_valid_json[0])) {
                raw_response = maybe_valid_json[0];
              }
            }

            return raw_response;
          },
        });

        const width = 608;
        const height = (2 * screen.availHeight) / 3;
        const left = screen.availLeft + (screen.availWidth - width) / 2;
        const top = screen.availTop + (screen.availHeight - height) / 2;

        taptreeModalHelper.modal = window.open(
          'https://checkout.taptree.org/lounge',
          '_blank',
          `popup, width=${width}, height=${height}, left=${left}, top=${top}`
        );
        taptreeModalHelper.setTimers();
        taptreeModalHelper.updateBlockerWhenModalReady();

        // Determine if we're on the order-pay page
        const isOrderPayPage = $form.is('#order_review');

        // Set the appropriate AJAX URL
        const ajaxUrl = isOrderPayPage
          ? '/wp-admin/admin-ajax.php?action=taptree_custom_pay_for_order' //taptree_modal_params.custom_checkout_url // Custom endpoint for order-pay
          : wc_checkout_params.checkout_url; // Default WooCommerce checkout endpoint

        console.log(ajaxUrl);

        // Prepare the data
        let data = $form.serialize();
        if (isOrderPayPage) {
          // Append order_id and key for order-pay
          data += `&order_id=${taptree_modal_params.order_id}&key=${taptree_modal_params.key}`;
        }

        try {
          const result = await $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: data,
            dataType: 'json',
          });

          try {
            if (
              'success' === result.result &&
              $form.triggerHandler('checkout_place_order_success', result) !==
                false
            ) {
              // Store the URLs for future redirects
              taptreeModalHelper.orderPayUrl = result.order_pay_url;
              taptreeModalHelper.thankYouUrl = result.thank_you_url;
              taptreeModalHelper.modal.location = result.redirect.startsWith(
                'https://'
              )
                ? result.redirect
                : decodeURI(result.redirect);
            } else if ('failure' === result.result) {
              throw 'Result failure';
            } else {
              throw 'Invalid response';
            }
          } catch (err) {
            taptreeModalHelper.closeModal();

            // Reload page if needed
            if (true === result.reload) {
              window.location.reload();
              return;
            }

            // Trigger update in case we need a fresh nonce
            if (true === result.refresh) {
              $(document.body).trigger('update_checkout');
            }

            // Add new errors
            if (result.messages) {
              taptreeCheckoutFormOverrides.submit_error(result.messages);
            } else {
              taptreeCheckoutFormOverrides.submit_error(
                '<div class="woocommerce-error">' +
                  wc_checkout_params.i18n_checkout_error +
                  '</div>'
              );
            }
          }
        } catch (error) {
          taptreeModalHelper.closeModal();

          taptreeCheckoutFormOverrides.submit_error(
            '<div class="woocommerce-error">' +
              (error.message || wc_checkout_params.i18n_checkout_error) +
              '</div>'
          );
        } finally {
          $form.removeClass('processing');
        }
      }

      return false;
    },
  };

  const wc_checkout_form = {
    is_valid_json: function (raw_json) {
      try {
        let json = JSON.parse(raw_json);
        return json && 'object' === typeof json;
      } catch (e) {
        return false;
      }
    },
    scroll_to_notices: function () {
      let scrollElement = $(
        '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout'
      );

      if (!scrollElement.length) {
        scrollElement = $('form.checkout');
      }
      $.scroll_to_notices(scrollElement);
    },
  };

  taptreeCheckoutFormOverrides.init();
});
