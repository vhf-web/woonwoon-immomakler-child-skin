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
            doneButton: false,
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
      const $reset = $('#immomakler-search-reset').length
        ? $('#immomakler-search-reset')
        : $module.find('#immomakler-search-reset').first();

      // Keep only the primary CTA in the actions row.
      const $actionsRow = $module.find('.search-actions.row').first();
      if ($actionsRow.length) {
        $actionsRow.find('.immomakler-more-options, .search-for-id, .btn-secondary, .immomakler-cart-button, .immomakler-cart-link').remove();
        $actionsRow.find('a.btn').not('.immomakler-submit').remove();

        // Put reset link on same line as CTA.
        if ($reset.length) {
          $reset.removeClass('btn btn-secondary').addClass('woonwoon-reset-link');
          // Ensure it sits before the CTA.
          if (!$actionsRow.find('#immomakler-search-reset').length) {
            $actionsRow.prepend($reset);
          } else {
            $actionsRow.find('#immomakler-search-reset').prependTo($actionsRow);
          }
        }
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

