<?php

namespace Drupal\digital_publication_title;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital publication title entities.
 *
 * @ingroup digital_publication_title
 */
class DigitalPublicationTitleListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital publication title ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_publication_title\Entity\DigitalPublicationTitle */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.digital_publication_title.edit_form',
      ['digital_publication_title' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
