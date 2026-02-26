/**
 * formHandler.js
 *
 * Manages form submissions and opens the TapTree Checkout popup via zoid.
 * Fallbacks:
 *   - Mobile → native form submit (redirect mode)
 *   - Popup blocked (PopupOpenError) → native form submit (redirect mode)
 */

import {CONTEXT} from '@krakenjs/zoid';
import {TapTreeCheckout} from './taptreeCheckout.js';

class FormHandler {
  /**
   * @param {UIManager} uiManager - Handles loading overlay.
   * @param {Object}    config    - Localized checkout/order params.
   */
  constructor(uiManager, config) {
    this.uiManager = uiManager;
    this.config = config;
    this.$checkoutForm = jQuery('form.checkout, form#order_review');
    this.popupInstance = null;
    this.popupWindow = null;
    this.fallbackToRedirect = false;
  }

  init() {
    // Use capture phase so we fire BEFORE WooCommerce's jQuery submit handler
    // (jQuery handlers fire in bubble phase). This prevents WooCommerce's
    // blockUI spinner from flashing before our overlay.
    const form = this.$checkoutForm[0];
    if (form) {
      form.addEventListener('submit', this.handleSubmit.bind(this), true);
    }
    this.listenForPostMessage();
  }

  /**
   * Handles form submission.
   * Opens a zoid popup on desktop; falls through to native submit on mobile
   * or when the popup is blocked.
   */
  async handleSubmit(e) {
    // Skip interception if we're in redirect fallback mode
    if (this.fallbackToRedirect) {
      return; // Let native submit through
    }

    const paymentMethod = this.getPaymentMethod();
    if (!paymentMethod || !paymentMethod.startsWith('taptree_wc_gateway_')) {
      return; // Not a TapTree gateway — let WooCommerce handle it
    }

    const gatewaysMap = taptree_modal_params.gateways || {};
    if (gatewaysMap[paymentMethod]) {
      return; // Gateway configured for redirect mode — native submit
    }

    if (this.isMobile()) {
      return; // Mobile — native submit (redirect mode)
    }

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const $form = jQuery(e.currentTarget);

    if ($form.hasClass('processing')) {
      return false;
    }

    // WooCommerce validation hooks
    if (
      $form.triggerHandler('checkout_place_order') === false ||
      $form.triggerHandler(
        `checkout_place_order_${paymentMethod}`
      ) === false
    ) {
      return false;
    }

    $form.addClass('processing');
    this.blockOnSubmit($form);

    try {
      await this.openZoidPopup($form);
    } catch (err) {
      if (err && err.message && err.message.indexOf('Popup') !== -1) {
        // Popup was blocked — fall back to native form submit (redirect mode).
        // Set flag BEFORE submit to prevent handleSubmit from intercepting again.
        console.warn('[TapTree] Popup blocked, falling back to redirect.');
        this.releaseUi($form);
        this.fallbackToRedirect = true;
        $form.submit();
        return;
      }
      console.error('[TapTree] Error during form submission:', err);
      this.releaseUi($form);
    }

    return false;
  }

  /**
   * Renders the zoid popup component.
   * AJAX starts immediately (in parallel with popup opening), not after onReady.
   * When the child signals ready AND AJAX is done, the popup navigates.
   */
  openZoidPopup($form) {
    return new Promise((resolve, reject) => {
      let paymentResolved = false;

      // Start AJAX immediately — runs in parallel with popup opening.
      // By the time the child page loads and calls onReady, AJAX may
      // already be done, making the navigate near-instant.
      const paymentPromise = this.createPaymentIntent($form);

      // zoid calls window.open() synchronously inside render() to keep the
      // user-gesture context (required for popup-unblocking). We intercept
      // briefly to grab the WindowProxy so we can later poll .closed and
      // distinguish "user closed the popup" from "popup navigated to an
      // external domain" (both fire zoid's onClose). Restored in finally{}.
      const origOpen = window.open;
      window.open = (...args) => {
        this.popupWindow = origOpen.apply(window, args);
        return this.popupWindow;
      };

      try {
        const instance = TapTreeCheckout({
          onReady: (actions) => {
            // AJAX may already be done — navigate as soon as both are ready.
            paymentPromise
              .then((result) => {
                this.config.orderPayUrl = result.order_pay_url;
                this.config.thankYouUrl = result.thank_you_url;
                actions.navigate(result.redirect);
              })
              .catch((err) => {
                console.error('[TapTree] Payment intent failed:', err);
                paymentResolved = true;
                this.closePopup();
                this.submitError(
                  this.config.i18n_checkout_error ||
                    'An error occurred during checkout.'
                );
                this.releaseUi($form);
                reject(err);
              });
          },

          onPaymentComplete: (redirectUrl) => {
            paymentResolved = true;
            this.popupInstance = null;
            if (!this.isSafeRedirectUrl(redirectUrl)) {
              this.releaseUi($form);
              resolve();
              return;
            }
            window.location = redirectUrl;
            resolve();
          },

          onPaymentCancel: () => {
            paymentResolved = true;
            this.popupInstance = null;
            this.releaseUi($form);
            resolve();
          },

          onError: (err) => {
            console.error('[TapTree] Payment error from popup:', err);
            paymentResolved = true;
            this.popupInstance = null;
            const target =
              this.config.orderPayUrl || this.config.checkoutUrl;
            this.releaseUi($form);
            window.location = target;
            resolve();
          },

          // zoid fires onClose when the child page unloads — including
          // cross-domain navigations, not only actual window close.
          // Poll the real window state instead of releasing UI.
          onClose: () => {
            if (paymentResolved) {
              this.popupInstance = null;
              resolve();
              return;
            }
            this.waitForPopupClose($form, resolve);
          },
        });

        this.popupInstance = instance;
        instance.render('body', CONTEXT.POPUP).catch((err) => {
          if (err && err.message && err.message.indexOf('Popup') !== -1) {
            reject(err);
          }
        });
      } catch (err) {
        reject(err);
      } finally {
        window.open = origOpen;
      }
    });
  }

  /**
   * Closes the popup if it's open.
   */
  closePopup() {
    if (this.popupInstance) {
      try {
        this.popupInstance.close();
      } catch (e) {
        // Popup may already be closed
      }
      this.popupInstance = null;
    }
    this.popupWindow = null;
  }

  /**
   * Waits for the popup window to actually close, then releases the UI.
   * zoid fires onClose when the child navigates to an external domain
   * (e.g. a PSP redirect), but the window itself is still open. This
   * method polls the standard WindowProxy.closed property to detect
   * when the user actually closes the popup.
   */
  waitForPopupClose($form, resolve) {
    const win = this.popupWindow;

    if (!win || win.closed) {
      this.popupInstance = null;
      this.popupWindow = null;
      this.releaseUi($form);
      resolve();
      return;
    }

    const poll = setInterval(() => {
      if (win.closed) {
        clearInterval(poll);
        this.popupInstance = null;
        this.popupWindow = null;
        this.releaseUi($form);
        resolve();
      }
    }, 500);
  }

  /**
   * Listens for postMessage from the spinner page fallback (Layer 2).
   * If zoid fails and the popup ends up on the merchant's spinner page,
   * it sends a postMessage with the redirect URL.
   */
  listenForPostMessage() {
    window.addEventListener('message', (event) => {
      // Only accept messages from our own origin (spinner page is same-origin)
      if (event.origin !== window.location.origin) return;

      const data = event.data;
      if (data && data.type === 'taptree_payment_complete' && data.redirectUrl) {
        if (!this.isSafeRedirectUrl(data.redirectUrl)) return;
        window.location = data.redirectUrl;
      }
    });
  }

  /**
   * Creates a payment intent via AJAX.
   * @param {jQuery} $form
   * @returns {Promise<Object>} - { redirect, order_pay_url, thank_you_url }
   */
  createPaymentIntent($form) {
    const isOrderPayPage = $form.is('#order_review');
    const ajaxUrl = isOrderPayPage
      ? '/wp-admin/admin-ajax.php?action=taptree_custom_pay_for_order'
      : this.config.checkoutUrl;

    let data = $form.serialize();
    if (isOrderPayPage) {
      data += `&order_id=${encodeURIComponent(this.config.order_id)}&key=${encodeURIComponent(this.config.key)}&security=${encodeURIComponent(this.config.security)}`;
    }

    return jQuery
      .ajax({
        type: 'POST',
        url: ajaxUrl,
        data: data,
        dataType: 'json',
      })
      .then((result) => {
        if (result.result === 'success' && result.redirect) {
          return {
            redirect: result.redirect,
            order_pay_url: result.order_pay_url,
            thank_you_url: result.thank_you_url,
          };
        }
        throw new Error(result.message || 'Invalid response from server');
      });
  }

  /**
   * Blocks the form UI during processing.
   */
  blockOnSubmit($form) {
    // Show our overlay first so it covers any WooCommerce blockUI spinner.
    this.uiManager.showLoadingOverlay();
    const isBlocked = $form.data('blockUI.isBlocked');
    if (isBlocked !== 1) {
      $form.block({
        message: null,
        overlayCSS: {background: '#fff', opacity: 0},
      });
    }
  }

  /**
   * Releases the form UI after error or cancel.
   */
  releaseUi($form) {
    $form.removeClass('processing');
    $form.unblock();
    this.uiManager.removeLoadingOverlay();
  }

  /**
   * Retrieves the selected payment method.
   */
  getPaymentMethod() {
    return this.$checkoutForm
      .find('input[name="payment_method"]:checked')
      .val();
  }

  /**
   * Validates a redirect URL is safe (https/http only, no javascript:/data: schemes).
   */
  isSafeRedirectUrl(url) {
    try {
      const parsed = new URL(url, window.location.origin);
      return parsed.protocol === 'https:' || parsed.protocol === 'http:';
    } catch (e) {
      return false;
    }
  }

  /**
   * Basic mobile detection. On mobile, we skip the popup and let
   * WooCommerce handle the redirect natively.
   */
  isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
      navigator.userAgent
    );
  }

  /**
   * Displays a WooCommerce checkout error.
   */
  submitError(message) {
    jQuery(
      '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
    ).remove();
    const $notice = jQuery(
      '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>'
    );
    $notice.text(message);
    this.$checkoutForm.prepend($notice);
    this.$checkoutForm
      .find('.input-text, select, input:checkbox')
      .trigger('validate')
      .trigger('blur');

    if (typeof wc_checkout_form !== 'undefined' && wc_checkout_form.scroll_to_notices) {
      wc_checkout_form.scroll_to_notices();
    }

    jQuery(document.body).trigger('checkout_error', [message]);
  }
}

export default FormHandler;
