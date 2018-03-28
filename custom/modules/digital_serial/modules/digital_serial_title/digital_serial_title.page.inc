<?php

/**
 * @file
 * Contains digital_serial_title.page.inc.
 *
 * Page callback for Digital serial title entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Digital serial title templates.
 *
 * Default template: digital_serial_title.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_digital_serial_title(array &$variables) {
  // Fetch DigitalSerialTitle Entity Object.
  $digital_serial_title = $variables['elements']['#digital_serial_title'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
