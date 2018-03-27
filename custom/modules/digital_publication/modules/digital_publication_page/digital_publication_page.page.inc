<?php

/**
 * @file
 * Contains digital_publication_page.page.inc.
 *
 * Page callback for Digital publication page entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Digital publication page templates.
 *
 * Default template: digital_publication_page.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_digital_publication_page(array &$variables) {
  // Fetch DigitalPublicationPage Entity Object.
  $digital_publication_page = $variables['elements']['#digital_publication_page'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
