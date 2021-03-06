<?php

/**
 * @file
 * Contains digital_serial_issue.page.inc.
 *
 * Page callback for Serial issue entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Serial issue templates.
 *
 * Default template: digital_serial_issue.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_digital_serial_issue(array &$variables) {
  // Fetch SerialIssue Entity Object.
  $digital_serial_issue = $variables['elements']['#digital_serial_issue'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
