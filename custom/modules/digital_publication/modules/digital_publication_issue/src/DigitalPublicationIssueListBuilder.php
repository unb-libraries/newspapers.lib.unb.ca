<?php

namespace Drupal\digital_publication_issue;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital publication issue entities.
 *
 * @ingroup digital_publication_issue
 */
class DigitalPublicationIssueListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital publication issue ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_publication_issue\Entity\DigitalPublicationIssue */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.digital_publication_issue.edit_form',
      ['digital_publication_issue' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
