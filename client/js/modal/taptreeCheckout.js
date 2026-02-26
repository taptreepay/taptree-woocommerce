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

export const TapTreeCheckout = create({
  tag: 'taptree-checkout',
  url: CHECKOUT_URL,
  dimensions: { width: '608px', height: '830px' },

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
