<?php

namespace Drupal\digital_serial_title\Controller;

use Drupal\node\Entity\Node;

/**
 * NewspaperPublicationDigitalTitleListTitleController object.
 */
class NewspaperPublicationDigitalTitleListTitleController {

  /**
   * Get title of herbarium specimen from Scientific Name of Assigned Taxon.
   */
  public function getDigitalListTitle($node) {
    $actualNode = Node::load($node);
    return $actualNode->getTitle();
  }

  /**
   * Get title of digital serial title.
   */
  public function getTitleIssuesTitle($digital_serial_title) {
    return 'This is a title';
  }

}
