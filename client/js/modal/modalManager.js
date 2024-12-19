import EventManager from './eventManager.js';

/**
 * ModalManager Class
 * Manages the lifecycle of the modal window, including opening, closing, and handling redirects.
 */
class ModalManager {
  constructor(uiManager, config) {
    this.uiManager = uiManager;
    this.config = config;
    this.eventManager = new EventManager(this);
    this.modal = null;
    this.modalTimeout = null;
    this.modalInterval = null;

    // Add a listener for taptree-specific custom events
    window.addEventListener(
      'taptree_event',
      this.handleTapTreeEvent.bind(this)
    );
  }

  /**
   * Handles custom TapTree events.
   * @param {CustomEvent} event - The custom event object.
   */
  handleTapTreeEvent(event) {
    const {type, redirectUrl} = event.detail;

    console.log('Received TapTree event:', event);

    if (type === 'redirected_to_origin' && redirectUrl) {
      console.log('Redirecting parent window to:', redirectUrl);

      this.clearTimers();
      this.uiManager.showLoadingOverlay();
      this.closeModal();
      // Redirect the main window to the received URL
      window.location = redirectUrl;

      this;
    } else {
      console.warn('Unhandled TapTree event or missing redirectUrl.');
    }
  }

  /**
   * Handles the window's unload event.
   */
  handleWindowUnload() {
    if (this.modal && !this.modal.closed) {
      console.log(
        'Window unloading: closing modal and redirecting to order-pay page.'
      );
      this.uiManager.showLoadingOverlay();
      this.redirectToOrderPay();
    }
  }

  /**
   * Attaches unload events via EventManager.
   */
  attachUnloadEvents() {
    this.eventManager.attachUnloadEvents();
  }

  /**
   * Detaches unload events via EventManager.
   */
  detachUnloadEvents() {
    this.eventManager.detachUnloadEvents();
  }

  /**
   * Opens the modal with the specified URL.
   * @param {string} url - The URL to open in the modal.
   */
  openModal(url) {
    const width = 608;
    const height = (2 * screen.availHeight) / 3;
    const left = (screen.availWidth - width) / 2;
    const top = (screen.availHeight - height) / 2;

    this.modal = window.open(
      url,
      '_blank',
      `popup, width=${width}, height=${height}, left=${left}, top=${top}`
    );

    this.setTimers();
    this.uiManager.updateBlockerText(
      'Du kannst das sichere TapTree Payments Browserfenster nicht sehen? Klicke hier um das Fenster anzuzeigen und deinen Kauf abzuschließen'
    );
    this.uiManager.appendBlockerHTML(`
      <p id="taptree-blocker-continue-text" style="text-align: center; color: #fff; font-weight: bold; text-decoration: underline;">Weiter</p>
    `);
    this.uiManager.appendBlockerHTML(`
      <button id="taptree-blocker-focus-button" style="position: fixed; height: 100%; width: 100%; top: 0; left: 0; cursor: pointer; background-color: transparent;" onClick="modalManager.focusModal()"></button>
    `);
    this.attachUnloadEvents();
  }

  /**
   * Sets the timers for modal timeout and interval checks.
   */
  setTimers() {
    this.modalTimeout = setTimeout(() => this.closeModal(), 15 * 60 * 1000);
    this.modalInterval = setInterval(() => this.modalHandler(), 500);
  }

  /**
   * Clears the modal timers.
   */
  clearTimers() {
    clearTimeout(this.modalTimeout);
    clearInterval(this.modalInterval);
  }

  /**
   * Handles the modal state by checking its status and performing necessary actions.
   */
  modalHandler() {
    try {
      if (!this.checkModal()) return;

      if (
        this.modal.location.hostname === window.location.hostname &&
        !this.isModalOnThankYouPage()
      ) {
        this.closeModalAndRedirect();
      } else if (this.isModalOnThankYouPage()) {
        this.handleThankYouPage();
      }
    } catch {
      // Suppress potential cross-origin errors
    }
  }

  /**
   * Checks if the modal is open and valid.
   * @returns {boolean} True if the modal is open and valid, false otherwise.
   */
  checkModal() {
    if (!this.modal || this.modal.closed) {
      this.clearTimers();
      this.uiManager.showLoadingOverlay();
      this.redirectToOrderPay();
      return false;
    }
    return true;
  }

  /**
   * Determines if the modal is currently on the thank-you page.
   * @returns {boolean} True if on the thank-you page, false otherwise.
   */
  isModalOnThankYouPage() {
    try {
      if (!this.modal || this.modal.closed) return false;

      const modalPath = this.modal.location.pathname;
      const thankYouPath = new URL(this.config.thankYouUrl).pathname;

      return modalPath === thankYouPath;
    } catch {
      return false;
    }
  }

  /**
   * Closes the modal window and redirects appropriately.
   */
  closeModalAndRedirect() {
    this.uiManager.showLoadingOverlay();
    this.closeModal();
    window.location = this.isModalOnThankYouPage()
      ? this.config.thankYouUrl
      : this.config.orderPayUrl;
  }

  /**
   * Handles redirection when the modal is on the thank-you page.
   */
  handleThankYouPage() {
    this.clearTimers();
    this.uiManager.showLoadingOverlay();
    this.closeModal();
    window.location = this.config.thankYouUrl;
  }

  /**
   * Closes the modal window.
   */
  closeModal() {
    if (this.modal && !this.modal.closed) {
      this.modal.close();
    }
    this.detachUnloadEvents();
  }

  /**
   * Redirects the main window to the order-pay URL.
   */
  redirectToOrderPay() {
    const target = this.config.orderPayUrl || this.config.checkoutUrl;
    this.uiManager.showLoadingOverlay();
    window.location = target;
  }

  /**
   * Prompts the user before unloading if the modal is open.
   * @param {Event} e - The event object.
   */
  promptUnloadIfModal(e) {
    if (this.modal) {
      //e.preventDefault();
      //e.returnValue = '';
    }
  }
}

export default ModalManager;
