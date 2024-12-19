/**
 * utils.js
 * Provides helper utilities for DOM manipulation and other shared logic.
 */

class ElementHelper {
  /**
   * Creates a wrapper for a DOM element.
   * @param {Element} element - The DOM element to wrap.
   */
  constructor(element) {
    this.el = element;
  }

  /**
   * Sets the text content of the element.
   * @param {string} text - The text to set.
   * @returns {ElementHelper} - The current instance for chaining.
   */
  setText(text) {
    this.el.innerText = text;
    return this;
  }

  /**
   * Appends HTML strings as child elements.
   * @param {...string} htmlStrings - The HTML strings to append.
   * @returns {ElementHelper} - The current instance for chaining.
   */
  appendHTML(...htmlStrings) {
    htmlStrings.forEach((htmlString) => {
      this.el.insertAdjacentHTML('beforeend', htmlString);
    });
    return this;
  }

  /**
   * Adds event listeners to the element.
   * @param {string} eventType - The event type.
   * @param {...Function} handlers - The event handlers to attach.
   * @returns {ElementHelper} - The current instance for chaining.
   */
  on(eventType, ...handlers) {
    handlers.forEach((handler) => {
      this.el.addEventListener(eventType, handler);
    });
    return this;
  }

  /**
   * Removes event listeners from the element.
   * @param {string} eventType - The event type.
   * @param {...Function} handlers - The event handlers to remove.
   * @returns {ElementHelper} - The current instance for chaining.
   */
  removeOn(eventType, ...handlers) {
    handlers.forEach((handler) => {
      this.el.removeEventListener(eventType, handler);
    });
    return this;
  }

  /**
   * Checks if the element matches a given selector.
   * @param {string} selectors - The CSS selector to match.
   * @returns {boolean} - Whether the element matches the selector.
   */
  is(selectors) {
    return this.el.matches(selectors);
  }
}

/**
 * Shortcut to create an ElementHelper for a selector.
 * @param {string} selector - The CSS selector to query.
 * @returns {ElementHelper|null} - The wrapped element or null if not found.
 */
const Q = (selector) => {
  const el = document.querySelector(selector);
  return el ? new ElementHelper(el) : null;
};

export {ElementHelper, Q};
