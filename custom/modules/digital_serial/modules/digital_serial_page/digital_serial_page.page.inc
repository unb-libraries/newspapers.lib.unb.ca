<?php

/**
 * @file
 * Contains digital_serial_page.page.inc.
 *
 * Page callback for Serial page entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Serial page templates.
 *
 * Default template: digital_serial_page.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_digital_serial_page(array &$variables) {
  // Fetch SerialPage Entity Object.
  $digital_serial_page = $variables['elements']['#digital_serial_page'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
