<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

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
    /* $header['page_image'] = $this->t('Preview'); */
    $header['page_image2'] = $this->t('Page Preview');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_page\Entity\SerialPage */
    $row['page_no'] = $entity->getPageNo();
    /* $image = $entity->getStyledImage('thumbnail'); */
    /* $row['page_image'] = render($image); */
    $linked_image = $entity->getLinkedStyledImage('thumbnail');
    $row['page_image2'] = $linked_image->toString();

    return $row + parent::buildRow($entity);
  }

}
