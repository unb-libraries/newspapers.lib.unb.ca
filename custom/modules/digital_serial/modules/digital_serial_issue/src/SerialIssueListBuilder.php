<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Serial issue entities.
 *
 * @ingroup digital_serial_issue
 */
class SerialIssueListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Serial issue ID');
    $header['issue_title'] = $this->t('Title');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_issue\Entity\SerialIssue */

    $row['id'] = $entity->id();
    $row['issue_title'] = Link::createFromRoute(
      $entity->getIssueTitle(),
      'entity.digital_serial_issue.edit_form',
      ['digital_serial_issue' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
