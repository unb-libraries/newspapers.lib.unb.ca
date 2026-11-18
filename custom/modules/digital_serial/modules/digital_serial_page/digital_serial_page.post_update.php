<?php

/**
 * @file
 * Post update functions for digital_serial_page.
 */

/**
 * Fixes a page numbered "05jpg" after a typo in its file name.
 *
 * The Queens County Gazette, 1898-08-24, page 5 came from
 * 005_QCG_1898_02_32_05jpg.jpg, so its number and sort key read "05jpg" and
 * it sorted after page 8. The page is changed only if its UUID and current
 * values are still the ones found on production.
 */
function digital_serial_page_post_update_fix_page_number_typo() {
  $page = \Drupal::entityTypeManager()->getStorage('digital_serial_page')->load(172885);
  if (!$page
    || $page->uuid() !== '3162c9e8-6c8e-496f-8e66-6aec7c406287'
    || $page->getPageNo() !== '05jpg'
    || $page->getPageSort() !== '05jpg') {
    return 'page 172885: skipped, not the expected page or value';
  }
  $page->setPageNo('05');
  $page->setPageSort('0005');
  $page->save();
  return "page 172885: page_no and page_sort '05jpg' -> '05' and '0005'";
}
