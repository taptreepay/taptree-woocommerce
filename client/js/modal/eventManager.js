/**
 * EventManager Class
 * Handles global browser events related to the modal lifecycle.
 *
 * Note: The modalManager instance MUST implement the following methods:
 * - handleTapTreeEvent(event): Handles custom 'taptree_event'.
 * - handleWindowUnload(): Handles 'unload' event to close modal or redirect.
 * - promptUnloadIfModal(e): Handles 'beforeunload' event for user prompts.
 */

class EventManager {
  constructor(modalManager) {
    if (
      !modalManager ||
      typeof modalManager.promptUnloadIfModal !== 'function'
    ) {
      throw new Error(
        'modalManager must implement promptUnloadIfModal(event).'
      );
    }

    this.modalManager = modalManager;
  }

  /**
   * Attaches all relevant events, including unload, postMessage, and custom events.
   */
  attachGlobalEvents() {
    this.attachUnloadEvents();
  }

  /**
   * Detaches all relevant events to prevent memory leaks.
   */
  detachGlobalEvents() {
    this.detachUnloadEvents();
  }

  /**
   * Attaches unload and refresh-related events.
   */
  attachUnloadEvents() {
    window.addEventListener(
      'beforeunload',
      this.modalManager.promptUnloadIfModal.bind(this.modalManager)
    );
  }

  /**
   * Detaches unload and refresh-related events.
   */
  detachUnloadEvents() {
    window.removeEventListener(
      'beforeunload',
      this.modalManager.promptUnloadIfModal.bind(this.modalManager)
    );
  }
}

export default EventManager;
