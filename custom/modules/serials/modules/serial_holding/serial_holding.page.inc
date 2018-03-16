<?php

/**
 * @file
 * Contains serial_holding.page.inc.
 *
 * Page callback for Serial holding entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Serial holding templates.
 *
 * Default template: serial_holding.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_serial_holding(array &$variables) {
  // Fetch SerialHolding Entity Object.
  $serial_holding = $variables['elements']['#serial_holding'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
