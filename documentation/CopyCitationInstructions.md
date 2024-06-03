#  NBNP Copy Citation Feature
This feature provides a means to copy text and/or link to clipboard, with alert tooltip.

## Installation
This feature depends on Bootstrap 4.x+ tooltips (see: https://getbootstrap.com/docs/4.6/components/tooltips/)
and zenorocha/clipboardjs JS library (see: https://github.com/zenorocha/clipboard.js)

- Add the minified Bootstrap bundle JS to your Drupal libraries yml:
  <pre><code>js:
    https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js:
      type: external
      attributes:
         integrity: 'sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx'
         crossorigin: anonymous
      minified: true
    dependencies:
      - core/jquery
  </code></pre>
    
  If you're using UNB Libraries Theme (see: https://github.com/unb-libraries/unb_lib_theme/tree/9.x-4.x) Bootstrap
  tooltips support is baked in as it includes the necessary minified bootstrap.bundle.min.js.

- Add the zenorocha/clipboardjs library available on github.com to your build's composer <b>repositories<b> section:
  <pre><code>"package": {
      "dist": {
        "type": "zip",
        "url": "https://github.com/zenorocha/clipboard.js/archive/refs/heads/master.zip"
      },
      "extra": {
        "installer-name": "clipboard.js"
      },
      "name": "zenorocha/clipboardjs",
      "type": "drupal-library",
      "version": "dev-master"
    },
    "type": "package"
  </code></pre>

- Add the Drupal clipboard.js API module and the JS library to your build's composer <b>require</b> section:
    <pre><code>"require": {
      ...
      "drupal/clipboardjs": "2.0.8",
      ...
      "zenorocha/clipboardjs": "dev-master",
      ...
    }</code></pre>
