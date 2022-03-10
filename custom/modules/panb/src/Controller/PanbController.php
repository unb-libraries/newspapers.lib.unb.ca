<?php

namespace Drupal\panb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Vocabulary;

use Symfony\Component\Yaml\Yaml;

/**
 * Provides route response for YAML parsing test controller.
 */
class PanbController extends ControllerBase {

  /**
   * Get the title of the homepage.
   *
   * @return string
   *   The title of the homepage.
   */
  public function getInfo() {
    $markup = NULL;
    $vocabulary = 'institution';

    if (empty(Vocabulary::load($vocabulary))) {
      $messenger = \Drupal::messenger();
      $message = "Warning: the vocabulary <$vid> does not exist!";
      $messenger->addMessage($message, 'error');
      \Drupal::logger('foo_taxonomy')->error($message);
    }

    $yml_file = 'panb.taxonomy.sample.terms.yml';
    $terms_yml_file_path = file_get_contents(drupal_get_path('module', 'panb') . '/data/' . $yml_file);
    if (empty($terms_yml_file_path)) {
      $markup .= 'Error: could not load the yaml file containing the term data.';
    }
    else {
      $terms_info = Yaml::parse($terms_yml_file_path);
      foreach ($terms_info[$vocabulary] as $term => $fields) {
        foreach ($fields as $field_name => $field_value) {
          $term_field_array[$term][$field_name] = $field_value;
          $term_field_array[$term]['vid'] = $vocabulary;
        }

        $markup .= '<br><b>Term #' . $term . ':</b><br>';
        $markup .= '<li>Creating term <code>test</code>';
        $markup .= '</ul>';
      }
    }

    return [
      '#markup' => $markup,
    ];
  }

}
