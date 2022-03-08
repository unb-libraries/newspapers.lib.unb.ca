<?php

namespace Drupal\panb\Controller;

use Drupal\Core\Controller\ControllerBase;
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
    $vid = 'institution';
    $term_fields = [
      'name',
      'french_label',
      'panb_code',
    ];

    $yml_file = 'panb.taxonomy.' . $vid . '.terms.yml';
    $terms_yml_file_path = file_get_contents(drupal_get_path('module', 'panb') . '/data/' . $yml_file);
    $terms_info = Yaml::parse($terms_yml_file_path);

    $markup = NULL;
    foreach ($terms_info[$vid] as $term => $fields) {
      $markup .= '<br><b>Term #' . $term . ':</b><br><ul>';
      foreach ($term_fields as $term_field) {
        $field_value = $fields[$term_field] ?? 'undefined';
        $markup .= '<li>The ' . $term_field . ' field is <kbd>' . $field_value . '</kbd></li>';
      }
      $markup .= '</ul>';
    }

    return [
      '#markup' => $markup,
    ];
  }

}
