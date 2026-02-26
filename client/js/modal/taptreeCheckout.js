/**
 * taptreeCheckout.js
 *
 * Shared zoid component definition for TapTree Checkout.
 * This must match the definition on the checkout service side (same tag, same props).
 * zoid uses window.name to persist child state across navigations — including
 * external PSP redirects — so the child context survives the full round trip.
 */

import {create} from '@krakenjs/zoid';

const CHECKOUT_URL =
  typeof taptree_modal_params !== 'undefined' &&
  taptree_modal_params.checkout_origin
    ? taptree_modal_params.checkout_origin + '/lounge'
    : 'https://checkout.taptree.org/lounge';

export const TapTreeCheckout = create({
  tag: 'taptree-checkout',
  url: CHECKOUT_URL,
  dimensions: {width: '608px', height: '667px'},

  // Custom prerender replaces zoid's default blue spinner with our branded one.
  // This HTML is written to the popup window immediately (before the URL loads).
  prerenderTemplate: ({doc}) => {
    const html = doc.createElement('html');
    const body = doc.createElement('body');
    const style = doc.createElement('style');
    const spinner = doc.createElement('div');
    spinner.classList.add('tt-spinner');

    style.appendChild(
      doc.createTextNode(`
        html, body {
          margin: 0;
          width: 100%;
          height: 100%;
          background: #fff;
        }
        body {
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .tt-spinner {
          width: 50px;
          height: 50px;
          border: 4px solid rgba(0, 0, 0, 0.1);
          border-top-color: rgba(0, 0, 0, 0.6);
          border-radius: 50%;
          animation: tt-spin 0.8s linear infinite;
        }
        @keyframes tt-spin {
          to { transform: rotate(360deg); }
        }
      `)
    );

    html.appendChild(body);
    body.appendChild(style);
    body.appendChild(spinner);
    return html;
  },

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
