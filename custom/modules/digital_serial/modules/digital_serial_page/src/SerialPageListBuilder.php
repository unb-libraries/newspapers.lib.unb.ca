<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Serial page entities.
 *
 * @ingroup digital_serial_page
 */
class SerialPageListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['page_no'] = $this->t('Page No');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_page\Entity\SerialPage */
    $row['page_no'] = $entity->getPageNo();
    return $row + parent::buildRow($entity);
  }

}
