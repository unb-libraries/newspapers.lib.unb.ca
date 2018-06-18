<?php

namespace Drupal\serial_holding;

use Drupal\taxonomy\Entity\Term;

/**
 * Defines an object to help with taxonomy operations.
 */
class TaxonomyHelper {

  /**
   * Creates the default taxonomy terms for serial holding types.
   */
  public static function addDefaultHoldingTypeTerms() {
    $config = \Drupal::config('serial_holding.taxonomy.serial_holding_types.default_terms');
    $holding_types = $config->get('items');

    foreach ($holding_types as $holding_type) {
      Term::create(
        [
          'parent' => [],
          'name' => $holding_type['name'],
          'vid' => 'serial_holding_types',
        ]
      )->save();
    }
  }

}
