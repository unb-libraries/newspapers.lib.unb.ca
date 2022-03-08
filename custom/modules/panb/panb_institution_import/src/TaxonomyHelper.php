<?php

namespace Drupal\panb\panb_institution_import;

use Drupal\taxonomy\Entity\Term;

/**
 * Defines an object to help with taxonomy operations.
 */
class TaxonomyHelper {

  /**
   * Create the default taxonomy terms for PANB Institution.
   */
  public static function addDefaultInstitutionTerms() {
    $config = \Drupal::config('panb.taxonomy.institution.default_terms');
    $terms = $config->get('terms');
    foreach ($terms as $term) {
      Term::create([
        'field_french_label' => $term['french_label'],
        'name' => $term['name'],
        'vid' => 'institution',
      ])
        ->save();
    }
  }

}
