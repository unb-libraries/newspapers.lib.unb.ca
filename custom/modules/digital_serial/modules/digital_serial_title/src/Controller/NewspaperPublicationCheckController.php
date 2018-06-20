<?php

namespace Drupal\digital_serial_title\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * NewspaperPublicationCheckController object.
 */
class NewspaperPublicationCheckController extends ControllerBase {

  /**
   * Check to see if a node is a newspaper publication.
   */
  public function checkAccess($node) {
    $actualNode = Node::load($node);
    return AccessResult::allowedIf($actualNode->bundle() === 'publication');
  }

}
