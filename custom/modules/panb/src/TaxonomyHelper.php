<?php

namespace Drupal\panb;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines an object to help with taxonomy operations.
 */
class TaxonomyHelper {

  /**
   * Create taxonomy terms for a given vocabulary/full path to a YAML file.
   */
  public static function addTermsFromYaml($vocabulary, $yaml_file_path) {
    // Ensure the given vocabulary vid exists.
    if (empty(Vocabulary::load($vocabulary))) {
      $message = "Warning: the vocabulary <$vocabulary> does not exist!";
      $messenger = \Drupal::messenger();
      $messenger->addMessage($message, 'error');
      return 0;
    }

    // Attempt to parse given yaml file.
    $terms_yml_file_path = file_get_contents($yaml_file_path);
    if (empty($terms_yml_file_path)) {
      $message = "Error: could not load the yaml file: $yml_file_path";
      $messenger = \Drupal::messenger();
      $messenger->addMessage($message, 'error');
      return 0;
    }

    $terms_from_yaml = Yaml::parse($terms_yml_file_path);
    foreach ($terms_from_yaml[$vocabulary] as $term => $fields) {
      $term_array_values = [];
      $term_array_values['vid'] = $vocabulary;
      foreach ($fields as $field_name => $field_value) {
        $term_array_values[$field_name] = $field_value;
      }
      // Hopefully we don't have a field typo in the YAML file.
      Term::create($term_array_values)->save();
    }
  }

}
