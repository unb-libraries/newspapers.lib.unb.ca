<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital serial page entities.
 *
 * @ingroup digital_serial_page
 */
class DigitalSerialPageListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital serial page ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_page\Entity\DigitalSerialPage */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.digital_serial_page.edit_form',
      ['digital_serial_page' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
