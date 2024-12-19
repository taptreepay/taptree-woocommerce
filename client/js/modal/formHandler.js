/**
 * formHandler.js
 * Manages form submissions, AJAX logic, and interactions with the TapTree Payment modal.
 */

class FormHandler {
  /**
   * Constructor for FormHandler.
   * @param {ModalManager} modalManager - Modal manager for handling modal operations.
   * @param {UIManager} uiManager - UI manager for handling loading overlays and other UI elements.
   * @param {Object} config - Parameters for checkout and order-pay.
   */
  constructor(modalManager, uiManager, config) {
    this.modalManager = modalManager;
    this.uiManager = uiManager;
    this.config = config; // Use shared config
    this.$checkoutForm = jQuery('form.checkout, form#order_review');
  }

  /**
   * Initializes the form handler by attaching event listeners.
   */
  init() {
    this.$checkoutForm.on('submit', this.handleSubmit.bind(this));
  }

  /**
   * Handles form submission and opens the modal for TapTree Payments.
   * @param {Event} e - The form submission event.
   */
  async handleSubmit(e) {
    const paymentMethod = this.getPaymentMethod();
    if (!paymentMethod.startsWith('taptree_wc_gateway_')) {
      return; // Ignore non-TapTree payment methods
    }

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const $form = jQuery(e.currentTarget);

    if ($form.hasClass('processing')) {
      return false; // Prevent duplicate processing
    }

    if (
      $form.triggerHandler('checkout_place_order') !== false &&
      $form.triggerHandler(
        `checkout_place_order_${this.getPaymentMethod()}`
      ) !== false
    ) {
      $form.addClass('processing');
      this.blockOnSubmit($form);

      this.modalManager.attachUnloadEvents();

      try {
        const modalUrl = await this.openModalWindow();
        await this.handleAjaxSubmission($form, modalUrl);
      } catch (err) {
        console.error('Error during form submission:', err);
      } finally {
        $form.removeClass('processing');
      }
    }

    return false;
  }

  /**
   * Blocks the form to prevent multiple submissions and shows a loading overlay.
   * @param {jQuery} $form - The jQuery form object.
   */
  blockOnSubmit($form) {
    const isBlocked = $form.data('blockUI.isBlocked');
    if (isBlocked !== 1) {
      $form.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0,
        },
      });
    }
    this.uiManager.showLoadingOverlay();
  }

  /**
   * Retrieves the selected payment method from the form.
   * @returns {string} - Selected payment method.
   */
  getPaymentMethod() {
    return this.$checkoutForm
      .find('input[name="payment_method"]:checked')
      .val();
  }

  /**
   * Opens the TapTree Payment modal.
   * @returns {Promise<string>} - The URL of the opened modal.
   */
  async openModalWindow() {
    const width = 608;
    const height = (2 * screen.availHeight) / 3;
    const left = screen.availLeft + (screen.availWidth - width) / 2;
    const top = screen.availTop + (screen.availHeight - height) / 2;

    this.modalManager.modal = window.open(
      'https://checkout.taptree.org/lounge',
      '_blank',
      `popup, width=${width}, height=${height}, left=${left}, top=${top}`
    );

    this.modalManager.setTimers();
    this.uiManager.updateBlockerWhenModalReady();

    return this.modalManager.modal.location.href;
  }

  /**
   * Submits the form data via AJAX to WooCommerce backend.
   * @param {jQuery} $form - The jQuery form object.
   */
  async handleAjaxSubmission($form) {
    const isOrderPayPage = $form.is('#order_review');
    const ajaxUrl = isOrderPayPage
      ? '/wp-admin/admin-ajax.php?action=taptree_custom_pay_for_order'
      : this.config.checkoutUrl;

    let data = $form.serialize();
    if (isOrderPayPage) {
      data += `&order_id=${this.config.order_id}&key=${this.config.key}`;
    }

    try {
      const result = await jQuery.ajax({
        type: 'POST',
        url: ajaxUrl,
        data: data,
        dataType: 'json',
      });

      if (result.result === 'success') {
        this.config.orderPayUrl = result.order_pay_url;
        this.config.thankYouUrl = result.thank_you_url;

        const redirectUrl = result.redirect.startsWith('https://')
          ? result.redirect
          : decodeURI(result.redirect);
        this.modalManager.modal.location = redirectUrl;
      } else {
        throw new Error('Invalid response');
      }
    } catch (err) {
      console.error('Error in handleAjaxSubmission:', err);
      this.modalManager.closeModal();
      this.submitError(this.config.i18n_checkout_error);
    }
  }

  /**
   * Displays an error on the form if AJAX fails.
   * @param {string} message - Error message.
   */
  submitError(message) {
    jQuery(
      '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
    ).remove();
    this.$checkoutForm.prepend(`
      <div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
        ${message}
      </div>
    `);
    this.modalManager.releaseUiAndCleanUp();
    this.$checkoutForm
      .find('.input-text, select, input:checkbox')
      .trigger('validate')
      .trigger('blur');
    wc_checkout_form.scroll_to_notices();
    jQuery(document.body).trigger('checkout_error', [message]);
  }
}

export default FormHandler;
