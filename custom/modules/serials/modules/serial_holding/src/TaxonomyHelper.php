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

  /**
   * Gets the current Holding types.
   *
   * @throws \Exception
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of TermInterface objects that describe holding types.
   */
  public static function getHoldingTypes() {
    return \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(
        [
          'vid' => 'serial_holding_types',
        ]
      );
  }

  /**
   * Gets the tid of a holding type if it exists.
   *
   * @throws \Exception
   *
   * @return int
   *   The tid of the holding type, 0 otherwise.
   */
  public static function getHoldingTermId($type_string) {
    $object = new static();
    $types = $object->getHoldingTypes();
    foreach ($types as $type) {
      if ($type->getName() == $type_string) {
        return $type->id();
      }
    }
    return 0;
  }

}
