<?php

namespace Drupal\serial_holding\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Serial holding entities.
 */
class SerialHoldingViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    return $data;
  }

}
