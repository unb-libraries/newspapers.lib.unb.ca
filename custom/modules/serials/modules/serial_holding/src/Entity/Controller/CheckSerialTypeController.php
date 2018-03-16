<?php

namespace Drupal\serial_holding\Entity\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * CheckSerialTypeController object.
 */
class CheckSerialTypeController extends ControllerBase {

  /**
   * Check to see if a node one that should show the serial holdings tab.
   *
   * @param int $node
   *   The node id of the cabinetry projects.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The AccessResult.
   */
  public function checkType($node) {
    $actual_node = Node::load($node);

    return AccessResult::allowedIf(
      $actual_node->bundle() == SERIAL_HOLDING_ENTITY_REF_TYPE
    );
  }

}
