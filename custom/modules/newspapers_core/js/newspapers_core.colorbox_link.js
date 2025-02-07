/**
 * @file
 * Add Colorbox to broad history | family | supplemental info | image links.
 */
(function ($) {
    let broadTitleHistoryLink = $('.field--name-field-supplemental-information .file--image > a');
    if (broadTitleHistoryLink != null) {
        $(broadTitleHistoryLink).addClass('colorbox');
        $(broadTitleHistoryLink).prop('title', broadTitleHistoryLink.text());
    }
})(jQuery);
