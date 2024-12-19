import {Q} from './utils.js';

// Define global text variables
const TEXT_LOADING_OVERLAY = 'Einen Moment bitte, wir leiten dich weiter.';
const TEXT_MODAL_READY =
  'Das sichere TapTree Payments-Bezahlfenster wird nicht angezeigt? Klick hier, um deinen Kauf abzuschließen.';
const TEXT_CONTINUE = 'Weiter';

/**
 * UIManager Class
 * Handles the display and manipulation of UI elements like blockers and loading overlays.
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
   * Displays the loading overlay with an optional message.
   * @param {string} [message=TEXT_LOADING_OVERLAY] - The message to display.
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
   * Updates the blocker UI for when the modal is ready.
   */
  updateBlockerWhenModalReady() {
    this.showLoadingOverlay(TEXT_MODAL_READY);

    Q('#taptree-blocker-textbox').appendHTML(`
      <p id="taptree-blocker-continue-text" style="text-align: center; color: #fff; font-weight: bold; text-decoration: underline;">
        ${TEXT_CONTINUE}
      </p>
    `);

    Q('#taptree-blocker').appendHTML(`
      <button id="taptree-blocker-focus-button" style="position: fixed; height: 100%; width: 100%; top: 0; left: 0; cursor: pointer; background-color: transparent;" onClick="modalManager.focusModal()"></button>
    `);
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
