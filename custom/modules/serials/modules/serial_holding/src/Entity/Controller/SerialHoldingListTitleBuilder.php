<?php

namespace Drupal\serial_holding\Entity\Controller;

use Drupal\node\Entity\Node;

class SerialHoldingListTitleBuilder {

  /**
   * {@inheritdoc}
   */
  public static function getTitle() {
    $nid = \Drupal::routeMatch()->getParameters()->get('node');
    $node = Node::load($nid);

    return t(
      '@node_title - Holdings',
      [
        '@node_title' => $node->getTitle(),
      ]
    );
  }

}
