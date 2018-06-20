<?php

namespace Drupal\digital_serial_title\Controller;

use Drupal\node\Entity\Node;

/**
 * NewspaperPublicationDigitalTitleListController object.
 */
class NewspaperPublicationDigitalTitleListController {
  /**
   * Get title of herbarium specimen from Scientific Name of Assigned Taxon.
   */
  public function getSpecimenTitle($node) {
    $actualNode = Node::load($node);
    return $actualNode
      ->get('field_taxonomy_tid')
      ->get(0)
      ->entity
      ->get('field_cmh_full_specimen_name')->first()->view();
  }

}
