/* global jQuery */
(function ($) {
  function initSelectpicker(context) {
    const $ctx = context ? $(context) : $(document);
    const $selects = $ctx.find('select.selectpicker');
    if (!$selects.length) return;

    const hasPlugin = typeof $.fn.selectpicker === 'function';
    if (!hasPlugin) return;

    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry/i.test(
      navigator.userAgent || ''
    );

    $selects.each(function () {
      const $el = $(this);
      try {
        if (isMobile) {
          $el.selectpicker('mobile');
          return;
        }

        if ($el.data('selectpicker')) {
          $el.selectpicker('refresh');
        } else {
          $el.selectpicker({
            tickIcon: 'glyphicon-check',
            doneButton: true,
            doneButtonText: 'markierte auswählen',
          });
        }
      } catch (_) {
        // ignore
      }
    });
  }

  function enhanceFilterBar(context) {
    const $ctx = context ? $(context) : $(document);
    const $modules = $ctx.find('[id$="immomakler-search-advanced"]');
    if (!$modules.length) return;

    $modules.each(function () {
      const $module = $(this);

      // Create header container (top-right wishlist).
      let $header = $module.children('.woonwoon-filter-header');
      if (!$header.length) {
        $header = $('<div class="woonwoon-filter-header" />');
        $module.prepend($header);
      }

      // Move wishlist button into header.
      const $wishlist = $module.find('.immomakler-cart-button, .immomakler-cart-link').first();
      if ($wishlist.length) {
        $wishlist
          .removeClass('btn-primary')
          .addClass('woonwoon-wishlist');
        $header.empty().append($wishlist);
      }

      // Move reset into a subtle secondary line under filters.
      const $collapse = $module.find('[id$="collapseable-search"]').first();
      const $panelBody = $collapse.find('.panel-body').first();
      const $ranges = $panelBody.find('.search-ranges').first();
      const $reset = $('#immomakler-search-reset').length
        ? $('#immomakler-search-reset')
        : $module.find('#immomakler-search-reset').first();

      if ($panelBody.length && $ranges.length && $reset.length) {
        let $secondary = $panelBody.children('.woonwoon-filter-secondary');
        if (!$secondary.length) {
          $secondary = $('<div class="woonwoon-filter-secondary" />');
          $secondary.insertAfter($ranges);
        }
        $reset.removeClass('btn btn-secondary').addClass('woonwoon-reset-link');
        $secondary.empty().append($reset);
      }

      // Keep only the primary CTA in the actions row.
      const $actionsRow = $module.find('.search-actions.row').first();
      if ($actionsRow.length) {
        $actionsRow.find('.immomakler-more-options, .search-for-id, .btn-secondary, .immomakler-cart-button, .immomakler-cart-link').remove();
        $actionsRow.find('a.btn').not('.immomakler-submit').remove();
      }
    });
  }

  function runEnhancements(context) {
    initSelectpicker(context);
    enhanceFilterBar(context);
  }

  $(function () {
    runEnhancements(document);
  });

  // Re-apply after plugin AJAX updates.
  $(document).ajaxComplete(function (_evt, _xhr, _settings) {
    runEnhancements(document);
  });
})(jQuery);

