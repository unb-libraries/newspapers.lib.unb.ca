<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital Serial Title entities.
 *
 * @ingroup digital_serial_title
 */
class SerialTitleListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital Serial Title ID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_title\Entity\SerialTitle */
    $row['id'] = Link::createFromRoute(
      $entity->id(),
      'entity.digital_serial_title.edit_form',
      ['digital_serial_title' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
