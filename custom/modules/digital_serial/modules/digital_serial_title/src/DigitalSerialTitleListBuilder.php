<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital serial title entities.
 *
 * @ingroup digital_serial_title
 */
class DigitalSerialTitleListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital serial title ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_title\Entity\DigitalSerialTitle */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.digital_serial_title.edit_form',
      ['digital_serial_title' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
