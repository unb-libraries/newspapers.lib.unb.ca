<?php

namespace Drupal\serial_holding\Entity\Controller;

use Drupal\node\Entity\Node;

/**
 * SerialHoldingListTitleBuilder object.
 */
class SerialHoldingListTitleBuilder {

  /**
   * {@inheritdoc}
   */
  public static function getTitle() {
    $nid = \Drupal::routeMatch()->getParameters()->get('node');
    $node = Node::load($nid);

    return $node->getTitle();
  }

}
