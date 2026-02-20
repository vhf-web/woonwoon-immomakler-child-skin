/* global jQuery */
(function ($) {
  const DEBOUNCE_MS = 500;
  const BUSY_RELEASE_MS = 1200;

  let t = null;
  let busy = false;
  let lastSerialized = null;

  function getForm() {
    return $('[data-immomakler="search-form"]').first();
  }

  function serializeForm($form) {
    try {
      return $form.serialize();
    } catch (_) {
      return null;
    }
  }

  function markBusyTemporarily() {
    busy = true;
    window.setTimeout(() => {
      busy = false;
    }, BUSY_RELEASE_MS);
  }

  function submitIfChanged() {
    if (busy) return;

    const $form = getForm();
    if (!$form.length) return;

    const serialized = serializeForm($form);
    if (!serialized) return;
    if (serialized === lastSerialized) return;
    lastSerialized = serialized;

    // Keep the "collapse" state stable (plugin reads it during AJAX loads).
    const $collapse = $('[id$="collapseable-search"]').first();
    if ($collapse.length) {
      const isOpen = $collapse.hasClass('in');
      $form.find('input[name="collapse"]').val(isOpen ? 'in' : 'out');
    }

    markBusyTemporarily();
    $form.trigger('submit');
  }

  function scheduleSubmit() {
    window.clearTimeout(t);
    t = window.setTimeout(submitIfChanged, DEBOUNCE_MS);
  }

  // Debounced autosubmit for slider changes.
  // Delegated handlers survive the plugin's AJAX reload of the form markup.
  $(document).on('change', '.immomakler-search-range-slider', scheduleSubmit);

  // Extra stability: submit after user releases the handle (mouse/touch/keyboard).
  $(document).on('mouseup touchend keyup', '.noUi-handle', scheduleSubmit);

  // If user clicks the Apply button, accept it immediately.
  $(document).on('click', '.immomakler-submit', function () {
    busy = false;
    window.clearTimeout(t);
    const $form = getForm();
    if ($form.length) lastSerialized = serializeForm($form);
  });
})(jQuery);

