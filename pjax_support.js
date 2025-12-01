/**
 * AutoSaleVPS shortcode initializer for the Argon theme with PJAX enabled.
 *
 * Drop this file into the Argon theme (or enqueue it with a child theme)
 * so the AutoSaleVPS app remounts every time PJAX swaps a page fragment.
 *
 * The script intentionally uses only the public globals that the plugin exposes
 * (`ASV_BOOTSTRAP` and the `.asv-root` container) so the plugin itself can
 * remain untouched.
 */
(function () {
  var READY_FLAG = 'data-asv-mounted-by-theme';

  function mountAutoSaleVpsBlocks() {
    if (!window.ASV_BOOTSTRAP) {
      // The plugin script is enqueued only when WordPress printed ASV_BOOTSTRAP.
      // Bail out silently and let the plugin retry once the shortcode runs
      // during a full page load.
      return;
    }

    var blocks = document.querySelectorAll('.asv-root');
    if (!blocks.length) {
      return;
    }

    blocks.forEach(function (block) {
      if (block.getAttribute(READY_FLAG) === '1') {
        return;
      }

      // Ask the official plugin bundle to do the real work by faking the
      // PJAX lifecycle event that it already subscribes to.
      document.dispatchEvent(new Event('pjax:complete'));
      block.setAttribute(READY_FLAG, '1');
    });
  }

  function patchPjaxLoaded() {
    var previous = typeof window.pjaxLoaded === 'function' ? window.pjaxLoaded : null;

    window.pjaxLoaded = function () {
      if (previous) {
        try {
          previous.call(window);
        } catch (error) {
          console.error('Argon PJAX hook failed', error);
        }
      }

      mountAutoSaleVpsBlocks();
    };
  }

  patchPjaxLoaded();

  document.addEventListener('DOMContentLoaded', mountAutoSaleVpsBlocks, {
    once: true,
  });
})();
