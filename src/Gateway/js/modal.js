let taptreeModalHelper = null;

class ElementHelper {
  constructor(element) {
    this.el = element;
  }

  setText(text) {
    this.el.innerText = text;
    return this.el;
  }

  appendHTML(...htmlStrings) {
    for (const htmlSting of htmlStrings) {
      this.el.insertAdjacentHTML("beforeend", htmlSting);
    }
    return this.el;
  }

  on(eventType, ...handlers) {
    for (const handler of handlers) {
      this.el.addEventListener(eventType, handler);
    }
    return this.el;
  }

  removeOn(eventType, ...handlers) {
    for (const handler of handlers) {
      this.el.removeEventListener(eventType, handler);
    }
    return this.el;
  }

  is(selectors) {
    return this.el.matches(selectors);
  }
}

const Q = (selector) => {
  if (document[selector]) {
    return new ElementHelper(document[selector]);
  }

  return new ElementHelper(document.querySelector(selector));
};

jQuery(function ($) {
  if (typeof wc_checkout_params === "undefined") {
    return false;
  }

  taptreeModalHelper = {
    modal: null,
    modalInterval: null,
    modalTimeout: null,
    blocker:
      '<div id="taptree-blocker" style="z-index: 1001; position: fixed; height: 100%; width: 100%; top: 0; left: 0; background-color: #000; opacity: 0.75;"><div id="taptree-blocker-textbox" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);"><p id="taptree-blocker-text" style="max-width: 350px; text-align: center; color: #fff">Wir öffnen das sicher ClimatePay Browserfenster für dich. Einen Moment bitte ...</p></div></div>',

    updateBlockerWhenModalReady: () => {
      const modalReadyBlockerText =
        "Du kannst das sichere ClimatePay Browserfenster nicht sehen? Klicke hier um das Fenster anzuzeigen und deinen Kauf abzuschließen";
      Q("#taptree-blocker-text").setText(modalReadyBlockerText);

      const continueParagraph =
        '<p id="taptree-blocker-continue-text" style="text-align: center; color: #fff; font-weight: bold; text-decoration: underline;">Weiter</p>';
      Q("#taptree-blocker-textbox").appendHTML(continueParagraph);

      const blockerButton =
        '<button id="taptree-blocker-focus-button" style="position: fixed; height: 100%; width: 100%; top: 0; left: 0; cursor: pointer; background-color: transparent;" onClick="taptreeModalHelper.focusModal()"></button>';
      Q("#taptree-blocker").appendHTML(blockerButton);
    },
    modalHandler: () => {
      try {
        if (!taptreeModalHelper.checkModal()) {
          return;
        }

        taptreeModalHelper.closeModalAndRedirect();
      } catch {}
    },

    closeModalAndRedirect: () => {
      if (
        taptreeModalHelper.modal.location.hostname === window.location.hostname
      ) {
        taptreeModalHelper.releaseUiAndCleanUp();

        if (!taptreeModalHelper.modal.location.pathname.includes("order-pay")) {
          window.location = taptreeModalHelper.modal.location;
        }

        taptreeModalHelper.closeModal();
      }
    },

    checkModal: () => {
      if (!taptreeModalHelper.modal || taptreeModalHelper.modal.closed) {
        taptreeModalHelper.releaseUiAndCleanUp();
        return 0;
      }
      return 1;
    },

    releaseUiAndCleanUp: () => {
      taptreeModalHelper.clearTimers();
      taptreeModalHelper.detachEvents();
      taptreeCheckoutFormOverrides.releaseUi();
    },

    setTimers: () => {
      taptreeModalHelper.modalTimeout = setTimeout(
        taptreeModalHelper.closeModal,
        15 * 60 * 1000 // 15min
      );
      taptreeModalHelper.modalInterval = setInterval(() => {
        taptreeModalHelper.modalHandler();
      }, 50);
    },

    clearTimers: () => {
      clearTimeout(taptreeModalHelper.modalTimeout);
      clearInterval(taptreeModalHelper.modalInterval);
    },

    closeModal: () => {
      if (taptreeModalHelper.modal && !taptreeModalHelper.modal.closed) {
        taptreeModalHelper.modal.close();
      }
      taptreeModalHelper.releaseUiAndCleanUp();
    },

    modalFocused: null,

    setModalFocused: (val) => {
      if (typeof val === "object" && val instanceof Event) {
        if (val.type === "blur") {
          taptreeModalHelper.modalFocused = true;
        }
      } else if (typeof val === "boolean") {
        taptreeModalHelper.modalFocused = val;
      }
    },

    alertIfModalNotFocused: () => {
      if (!taptreeModalHelper.modalFocused) {
        window.alert(
          "Bitte wechsle den Browsertab um die ClimatePay Zahlung abzuschließen"
        );
      }
      window.removeEventListener("blur", taptreeModalHelper.setModalFocused);
    },

    focusModal: () => {
      taptreeModalHelper.setModalFocused(false);
      if (taptreeModalHelper.modal && !taptreeModalHelper.modal.closed) {
        window.addEventListener("blur", taptreeModalHelper.setModalFocused);
        taptreeModalHelper.modal.focus();
        setTimeout(taptreeModalHelper.alertIfModalNotFocused, 150);
      }
    },

    promptUnloadIfModal: (e) => {
      if (taptreeModalHelper.modal) {
        e.preventDefault();
        e.returnValue = "";
      }
    },

    attachUnloadEvents: () => {
      window.addEventListener(
        "beforeunload",
        taptreeModalHelper.promptUnloadIfModal
      );
      window.addEventListener("unload", taptreeModalHelper.closeModal);
    },

    detachEvents: () => {
      window.removeEventListener(
        "beforeunload",
        taptreeModalHelper.promptUnloadIfModal
      );
      window.removeEventListener("unload", taptreeModalHelper.closeModal);
      window.removeEventListener("blur", taptreeModalHelper.setModalFocused);
    },
  };

  const taptreeCheckoutFormOverrides = {
    $checkout_form: $("form.checkout"),

    blockOnSubmit: function ($form) {
      let isBlocked = $form.data("blockUI.isBlocked");

      if (1 !== isBlocked) {
        $form.block({
          message: null,
          overlayCSS: {
            background: "#fff",
            opacity: 0,
          },
        });
      }

      $(taptreeModalHelper.blocker).appendTo("body");
    },

    get_payment_method: function () {
      return taptreeCheckoutFormOverrides.$checkout_form
        .find('input[name="payment_method"]:checked')
        .val();
    },

    init: function () {
      this.$checkout_form.on("submit", this.submit);
    },

    releaseUi: function () {
      taptreeCheckoutFormOverrides.$checkout_form
        .removeClass("processing")
        .unblock();
      $("#taptree-blocker").remove();
    },

    submit_error: function (error_message) {
      $(
        ".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message"
      ).remove();
      taptreeCheckoutFormOverrides.$checkout_form.prepend(
        '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
          error_message +
          "</div>"
      ); // eslint-disable-line max-len
      taptreeModalHelper.releaseUiAndCleanUp();
      taptreeCheckoutFormOverrides.$checkout_form
        .find(".input-text, select, input:checkbox")
        .trigger("validate")
        .trigger("blur");
      wc_checkout_form.scroll_to_notices();
      $(document.body).trigger("checkout_error", [error_message]);
    },

    submit: async function (e) {
      if (
        document.querySelector('input[name="payment_method"]:checked').value !==
        "taptree_wc_gateway_hosted_checkout"
      ) {
        return;
      }
      console.log(e);

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      //taptreeCheckoutFormOverrides.reset_update_checkout_timer();
      let $form = $(this);

      if ($form.is(".processing")) {
        return false;
      }

      // Trigger a handler to let gateways manipulate the checkout if needed
      // eslint-disable-next-line max-len
      if (
        $form.triggerHandler("checkout_place_order") !== false &&
        $form.triggerHandler(
          "checkout_place_order_" +
            taptreeCheckoutFormOverrides.get_payment_method()
        ) !== false
      ) {
        $form.addClass("processing");

        taptreeCheckoutFormOverrides.blockOnSubmit($form);

        taptreeModalHelper.attachUnloadEvents();

        // ajaxSetup is global, but we use it to ensure JSON is valid once returned.
        await $.ajaxSetup({
          dataFilter: function (raw_response, dataType) {
            // We only want to work with JSON
            if ("json" !== dataType) {
              return raw_response;
            }

            if (wc_checkout_form.is_valid_json(raw_response)) {
              return raw_response;
            } else {
              // Attempt to fix the malformed JSON
              let maybe_valid_json = raw_response.match(/{"result.*}/);

              if (null === maybe_valid_json) {
                console.log("Unable to fix malformed JSON");
              } else if (wc_checkout_form.is_valid_json(maybe_valid_json[0])) {
                console.log("Fixed malformed JSON. Original:");
                console.log(raw_response);
                raw_response = maybe_valid_json[0];
              } else {
                console.log("Unable to fix malformed JSON");
              }
            }

            return raw_response;
          },
        });

        const width = 608;
        const height = (2 * screen.availHeight) / 3;
        const left = screen.availLeft + (screen.availWidth - width) / 2;
        const top = screen.availTop + (screen.availHeight - height) / 2;

        taptreeModalHelper.modal = window.open(
          "about:blank",
          "_blank",
          `popup, width=${width}, height=${height}, left=${left}, top=${top}`
        );
        taptreeModalHelper.setTimers();

        const checkoutRequestParams = {
          type: "POST",
          url: wc_checkout_params.checkout_url,
          dataType: "json",
          // async: false,
        };

        await $.ajax({
          ...checkoutRequestParams,
          data: $form.serialize(),
          success: function (result) {
            try {
              if (
                "success" === result.result &&
                $form.triggerHandler("checkout_place_order_success", result) !==
                  false
              ) {
                taptreeModalHelper.modal.location = result.redirect.startsWith(
                  "https://"
                )
                  ? result.redirect
                  : decodeURI(result.redirect);

                taptreeModalHelper.updateBlockerWhenModalReady();
              } else if ("failure" === result.result) {
                throw "Result failure";
              } else {
                throw "Invalid response";
              }
            } catch (err) {
              taptreeModalHelper.closeModal();

              // Reload page
              if (true === result.reload) {
                window.location.reload();
                return;
              }

              // Trigger update in case we need a fresh nonce
              if (true === result.refresh) {
                $(document.body).trigger("update_checkout");
              }

              // Add new errors
              if (result.messages) {
                taptreeCheckoutFormOverrides.submit_error(result.messages);
              } else {
                taptreeCheckoutFormOverrides.submit_error(
                  '<div class="woocommerce-error">' +
                    wc_checkout_params.i18n_checkout_error +
                    "</div>"
                ); // eslint-disable-line max-len
              }
            }
          },
          error: function (jqXHR, textStatus, errorThrown) {
            taptreeModalHelper.closeModal();

            taptreeCheckoutFormOverrides.submit_error(
              '<div class="woocommerce-error">' +
                (errorThrown || wc_checkout_params.i18n_checkout_error) +
                "</div>"
            );
          },
        });
      }

      return false;
    },
  };

  const wc_checkout_form = {
    is_valid_json: function (raw_json) {
      try {
        let json = JSON.parse(raw_json);

        return json && "object" === typeof json;
      } catch (e) {
        return false;
      }
    },
    scroll_to_notices: function () {
      let scrollElement = $(
        ".woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout"
      );

      if (!scrollElement.length) {
        scrollElement = $("form.checkout");
      }
      $.scroll_to_notices(scrollElement);
    },
  };

  taptreeCheckoutFormOverrides.init();
});
