/**
 * modal.js
 *
 * Entry point for TapTree Payment popup integration with WooCommerce.
 * Uses zoid for reliable cross-domain popup communication.
 */

import FormHandler from './formHandler.js';
import UIManager from './uiManager.js';

jQuery(function ($) {
  if (typeof wc_checkout_params === 'undefined') {
    return false;
  }

  const config = {
    orderPayUrl: null,
    thankYouUrl: null,
    checkoutUrl: wc_checkout_params.checkout_url,
    i18n_checkout_error: wc_checkout_params.i18n_checkout_error,
    order_id: taptree_modal_params?.order_id || '',
    key: taptree_modal_params?.key || '',
    security: taptree_modal_params?.security || '',
  };

  const uiManager = new UIManager();
  const formHandler = new FormHandler(uiManager, config);
  formHandler.init();
});
