<?php

/**
 * @file
 * Contains digital_publication_title.page.inc.
 *
 * Page callback for Digital publication title entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Digital publication title templates.
 *
 * Default template: digital_publication_title.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_digital_publication_title(array &$variables) {
  // Fetch DigitalPublicationTitle Entity Object.
  $digital_publication_title = $variables['elements']['#digital_publication_title'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
