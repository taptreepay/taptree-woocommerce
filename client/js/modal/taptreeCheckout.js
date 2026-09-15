/**
 * taptreeCheckout.js
 *
 * Shared zoid component definition for TapTree Checkout.
 * This must match the definition on the checkout service side (same tag, same props).
 * zoid uses window.name to persist child state across navigations — including
 * external PSP redirects — so the child context survives the full round trip.
 */

import { create } from '@krakenjs/zoid';

const CHECKOUT_URL =
  typeof taptree_modal_params !== 'undefined' &&
  taptree_modal_params.checkout_origin
    ? taptree_modal_params.checkout_origin + '/lounge'
    : 'https://checkout.taptree.org/lounge';

/**
 * Popup size in CSS pixels. Also used by the opener to correct browsers that
 * ignore the width/height in the window-features string.
 */
export const POPUP_DIMENSIONS = { width: 608, height: 830 };

export const TapTreeCheckout = create({
  tag: 'taptree-checkout',
  url: CHECKOUT_URL,
  dimensions: {
    width: `${POPUP_DIMENSIONS.width}px`,
    height: `${POPUP_DIMENSIONS.height}px`,
  },

  // Disable zoid's default blue prerender spinner.
  // The popup shows a blank white page until the lounge URL loads.
  prerenderTemplate: () => null,

  props: {
    onReady: {
      type: 'function',
      required: true,
    },
    onPaymentComplete: {
      type: 'function',
      required: true,
    },
    onPaymentCancel: {
      type: 'function',
      required: false,
    },
    onError: {
      type: 'function',
      required: false,
    },
  },
});
