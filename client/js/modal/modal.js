/**
 * modal.js
 *
 * Initializes and orchestrates the TapTree Payment modal integration with WooCommerce.
 * Handles UI elements, modal lifecycle, global events, and form submissions.
 */

import FormHandler from './formHandler.js';
import ModalManager from './modalManager.js';
import UIManager from './uiManager.js';

jQuery(function ($) {
  // Exit if WooCommerce checkout parameters are missing
  if (typeof wc_checkout_params === 'undefined') {
    return false;
  }

  // Shared configuration object, which is injected into the modal manager and form handler
  // Note that wc_checkout_params is injected either by WooCommerce or by the plugin
  // cf. wordpress/taptree-payments-for-woocommerce/src/Gateway/TapTreePaymentGateway.php
  const config = {
    orderPayUrl: null, // will be set dynamically
    thankYouUrl: null, // will be set dynamically
    checkoutUrl: wc_checkout_params.checkout_url,
    i18n_checkout_error: wc_checkout_params.i18n_checkout_error,
    order_id: taptree_modal_params?.order_id || '',
    key: taptree_modal_params?.key || '',
  };

  // Initialize UI manager for handling loading overlays and blockers
  const uiManager = new UIManager();

  // Initialize modal manager for controlling the modal lifecycle
  const modalManager = new ModalManager(uiManager, config);

  // Initialize form handler for submission logic and modal interactions
  const formHandler = new FormHandler(modalManager, uiManager, config);

  // Attach form events and global unload events
  formHandler.init();
  modalManager.attachUnloadEvents();
});
