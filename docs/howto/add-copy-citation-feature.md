---
title: Add the copy-citation feature
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-06-18
last_reviewed_by: jsanford@unb.ca
description: Developer setup for the NBNP copy-to-clipboard citation control.
---

# Add the copy-citation feature

## Goal

Enable the copy-citation control, which copies citation text or a link to the
clipboard and shows a confirmation tooltip.

## Prerequisites

This feature depends on:

- [Bootstrap 4.x+ tooltips](https://getbootstrap.com/docs/4.6/components/tooltips/).
- The [zenorocha/clipboard.js](https://github.com/zenorocha/clipboard.js) library.

If you use the
[UNB Libraries Theme (9.x-4.x)](https://github.com/unb-libraries/unb_lib_theme/tree/9.x-4.x),
Bootstrap tooltip support is already included, so you can skip the Bootstrap step
below.

## Steps

1. If you are not using the UNB Libraries Theme, add the minified Bootstrap bundle to
   your Drupal library definition:

   ```yaml
   js:
     https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js:
       type: external
       attributes:
         integrity: 'sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx'
         crossorigin: anonymous
       minified: true
   dependencies:
     - core/jquery
   ```

2. Add the clipboard.js library to the `repositories` section of your build's
   `composer.json`:

   ```json
   {
     "type": "package",
     "package": {
       "name": "zenorocha/clipboardjs",
       "version": "dev-master",
       "type": "drupal-library",
       "dist": {
         "type": "zip",
         "url": "https://github.com/zenorocha/clipboard.js/archive/refs/heads/master.zip"
       },
       "extra": {
         "installer-name": "clipboard.js"
       }
     }
   }
   ```

3. Add the Drupal clipboard.js API module and the library to the `require` section of
   your build's `composer.json`:

   ```json
   {
     "drupal/clipboardjs": "2.0.8",
     "zenorocha/clipboardjs": "dev-master"
   }
   ```

## Verify the result

Rebuild the application and load a page with a citation control. Selecting it copies
the citation to the clipboard and shows the confirmation tooltip.
