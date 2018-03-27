<?php

namespace Drupal\digital_publication_page;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital publication page entities.
 *
 * @ingroup digital_publication_page
 */
class DigitalPublicationPageListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital publication page ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_publication_page\Entity\DigitalPublicationPage */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.digital_publication_page.edit_form',
      ['digital_publication_page' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
