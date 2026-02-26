import {Q} from './utils.js';

const TEXT_LOADING_OVERLAY = 'Einen Moment bitte, wir leiten dich weiter.';

/**
 * UIManager
 * Manages the loading overlay shown while the popup is processing.
 */
class UIManager {
  constructor() {
    this.blockerHTML = `
      <div id="taptree-blocker" style="z-index: 1001; position: fixed; height: 100%; width: 100%; top: 0; left: 0; background-color: #000; opacity: 0.75;">
        <div id="taptree-blocker-textbox" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);">
          <p id="taptree-blocker-text" style="max-width: 350px; text-align: center; color: #fff">
            ${TEXT_LOADING_OVERLAY}
          </p>
        </div>
      </div>
    `;
  }

  /**
   * Shows the loading overlay with a spinner.
   * @param {string} [message] - Optional custom message.
   */
  showLoadingOverlay(message = TEXT_LOADING_OVERLAY) {
    if (!document.querySelector('#taptree-blocker')) {
      document.body.insertAdjacentHTML('beforeend', this.blockerHTML);
    }
    Q('#taptree-blocker-text').setText(message);

    if (!document.querySelector('#taptree-spinner-container')) {
      Q('#taptree-blocker-textbox').appendHTML(`
        <div id="taptree-spinner-container" style="margin-top:20px; text-align:center;">
          <div style="display:inline-block; width:40px; height:40px; border:4px solid #fff; border-radius:50%; border-top-color:transparent; animation: spin 1s linear infinite;"></div>
          <style>@keyframes spin { to {transform: rotate(360deg);} }</style>
        </div>
      `);
    }
  }

  /**
   * Removes the loading overlay from the DOM.
   */
  removeLoadingOverlay() {
    const blocker = document.querySelector('#taptree-blocker');
    if (blocker) blocker.remove();
  }
}

export default UIManager;
