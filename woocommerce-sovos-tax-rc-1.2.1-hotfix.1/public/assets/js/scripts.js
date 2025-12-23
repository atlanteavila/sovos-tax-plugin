(function ($) {
  const settings = window.sovosQuoteData || {};
  const isExempt = !!settings.isExempt;

  let quoteFresh = isExempt ? true : !!settings.isFresh;
  let ajaxBusy = false;
  let staleSyncing = false;
  let captureBound = false;

  const selectors = {
    shippingInputs: '.woocommerce-shipping-fields :input, #ship-to-different-address-checkbox',
    calculateButton: '.sovos-calc-taxes',
    saveAddress: 'button[name="save_address"], .button.save_address',
    placeOrder: '#place_order',
    helpText: '.sovos-calc-taxes-help',
  };

  const $help = $(selectors.helpText);
  const defaultHelp = $help.text();

  function getStateField() {
    let $field = $('input[name="sovos_quote_state"]');

    if (!$field.length) {
      $field = $('<input>', { type: 'hidden', name: 'sovos_quote_state', value: '' });
      $('form.checkout').append($field);
    }

    return $field;
  }

  function updateHelp(message, isError) {
    if (!$help.length) {
      return;
    }

    $help.text(message || defaultHelp);
    $help.toggleClass('sovos-calc-error', !!isError);
  }

  function syncControls() {
    const $placeOrder = $(selectors.placeOrder);
    const $calculate = $(selectors.calculateButton);
    const $stateField = getStateField();

    if ($stateField.length) {
      $stateField.val(quoteFresh ? 'fresh' : 'stale');
    }

    if ($placeOrder.length) {
      $placeOrder.prop('disabled', !quoteFresh || ajaxBusy);
    }

    if ($calculate.length) {
      $calculate.prop('disabled', ajaxBusy);
      $calculate.toggleClass('is-busy', ajaxBusy);
      if (settings.labels) {
        $calculate.text(ajaxBusy ? settings.labels.calculating : settings.labels.calculate);
      }
    }
  }

  function postAjax(action, onAlways) {
    if (!settings.ajaxUrl || !settings.nonce) {
      return;
    }

    $.post(settings.ajaxUrl, {
      action,
      security: settings.nonce,
    }).always(function () {
      if (typeof onAlways === 'function') {
        onAlways();
      }
    });
  }

  function markStale() {
    if (isExempt) {
      return;
    }

    if (!quoteFresh && !ajaxBusy) {
      return;
    }

    quoteFresh = false;
    syncControls();

    if (staleSyncing) {
      return;
    }

    staleSyncing = true;
    postAjax('sovos_mark_quote_stale', function () {
      staleSyncing = false;
    });
  }

  function requestQuote(event) {
    if (event) {
      event.preventDefault();
    }

    if (isExempt) {
      return;
    }

    if (ajaxBusy || quoteFresh) {
      return;
    }

    ajaxBusy = true;
    updateHelp(defaultHelp, false);
    syncControls();

    $.ajax({
      url: settings.ajaxUrl,
      method: 'POST',
      data: {
        action: 'sovos_refresh_quote',
        security: settings.nonce,
      },
    })
      .done(function (response) {
        if (response && response.success) {
          quoteFresh = true;
          syncControls();
          $(document.body).trigger('update_checkout', { sovosQuoteRefresh: true });
        } else {
          quoteFresh = false;
          const message = response && response.data && response.data.message ? response.data.message : (settings.labels ? settings.labels.error : '');
          updateHelp(message || defaultHelp, true);
        }
      })
      .fail(function () {
        quoteFresh = false;
        updateHelp((settings.labels && settings.labels.error) || defaultHelp, true);
      })
      .always(function () {
        ajaxBusy = false;
        syncControls();
      });
  }

  function bindShippingCaptureListeners() {
    if (captureBound) {
      return;
    }

    const handler = function (event) {
      const target = event.target;
      if (!target || !(target instanceof HTMLElement)) {
        return;
      }

      if (target.id === 'ship-to-different-address-checkbox' || target.closest('.woocommerce-shipping-fields')) {
        markStale();
      }
    };

    document.addEventListener('change', handler, true);
    document.addEventListener('input', handler, true);
    captureBound = true;
  }

  $(function () {
    syncControls();
    bindShippingCaptureListeners();

    $(document.body).on('update_checkout', function () {
      syncControls();
    });
    $(document.body).on('updated_checkout', function (_event, args) {
      if (args && args.sovosQuoteRefresh) {
        quoteFresh = true;
      }
      syncControls();
    });

    $(document).on('click', selectors.calculateButton, requestQuote);
    $(document).on('click', selectors.saveAddress, requestQuote);
    $('form.checkout').on('change input', selectors.shippingInputs, markStale);
  });
})(jQuery);
