<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Serial issue entities.
 */
class SerialIssueViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
