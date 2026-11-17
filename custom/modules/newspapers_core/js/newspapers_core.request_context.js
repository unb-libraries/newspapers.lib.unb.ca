/**
 * @file
 * Fills in the parts of a page that depend on the visitor's request.
 *
 * Pages are cached and shared between visitors, so the server leaves out the
 * date a citation was accessed and the link back to the visitor's search.
 */
(function (Drupal, once) {
  'use strict';

  // Must match _newspapers_core_get_citation_accessed_date_markup().
  var ACCESSED_PLACEHOLDER = '[date accessed]';

  /**
   * Returns today's date as the citations format it, e.g. "November 07, 2026".
   */
  function accessedDate() {
    return new Date().toLocaleDateString('en-US', {
      month: 'long',
      day: '2-digit',
      year: 'numeric',
    });
  }

  /**
   * Returns the referring URL if it is a search on this site, otherwise null.
   */
  function searchReferrer() {
    if (!document.referrer) {
      return null;
    }
    var url;
    try {
      url = new URL(document.referrer);
    }
    catch (e) {
      return null;
    }
    if (url.origin !== window.location.origin || url.href.indexOf('search') === -1) {
      return null;
    }
    return url.href;
  }

  Drupal.behaviors.newspapersCoreCitationAccessedDate = {
    attach: function (context) {
      var date = accessedDate();
      once('citation-accessed-date', '.citation-accessed-date', context).forEach(function (element) {
        element.textContent = date;
      });
      once('citation-accessed-copy', '[data-clipboard-text]', context).forEach(function (element) {
        var text = element.getAttribute('data-clipboard-text');
        element.setAttribute('data-clipboard-text', text.split(ACCESSED_PLACEHOLDER).join(date));
      });
    }
  };

  Drupal.behaviors.newspapersCoreBackToSearch = {
    attach: function (context) {
      var referrer = searchReferrer();
      if (!referrer) {
        return;
      }
      once('back-to-search', 'a[data-back-to-search]', context).forEach(function (link) {
        link.setAttribute('href', referrer);
        link.textContent = link.getAttribute('data-back-to-search');
        var wrapper = link.closest('[data-back-to-search-wrapper]');
        if (wrapper) {
          wrapper.hidden = false;
        }
      });
    }
  };

})(Drupal, once);
