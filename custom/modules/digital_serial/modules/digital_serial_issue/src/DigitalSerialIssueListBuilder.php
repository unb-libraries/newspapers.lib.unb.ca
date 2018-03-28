<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Digital serial issue entities.
 *
 * @ingroup digital_serial_issue
 */
class DigitalSerialIssueListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Digital serial issue ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_issue\Entity\DigitalSerialIssue */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.digital_serial_issue.edit_form',
      ['digital_serial_issue' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
