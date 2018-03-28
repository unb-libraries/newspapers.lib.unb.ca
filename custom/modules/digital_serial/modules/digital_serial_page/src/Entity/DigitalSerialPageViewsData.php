<?php

namespace Drupal\digital_serial_page\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Digital serial page entities.
 */
class DigitalSerialPageViewsData extends EntityViewsData {

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
