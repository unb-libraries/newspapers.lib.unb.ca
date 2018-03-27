<?php

namespace Drupal\digital_publication_page\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Digital publication page entities.
 */
class DigitalPublicationPageViewsData extends EntityViewsData {

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
