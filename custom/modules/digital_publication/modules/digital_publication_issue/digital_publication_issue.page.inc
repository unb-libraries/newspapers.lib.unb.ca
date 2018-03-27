<?php

/**
 * @file
 * Contains digital_publication_issue.page.inc.
 *
 * Page callback for Digital publication issue entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Digital publication issue templates.
 *
 * Default template: digital_publication_issue.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_digital_publication_issue(array &$variables) {
  // Fetch DigitalPublicationIssue Entity Object.
  $digital_publication_issue = $variables['elements']['#digital_publication_issue'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
