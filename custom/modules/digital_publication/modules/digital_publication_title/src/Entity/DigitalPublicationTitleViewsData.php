<?php

namespace Drupal\digital_publication_title\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Digital publication title entities.
 */
class DigitalPublicationTitleViewsData extends EntityViewsData {

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
