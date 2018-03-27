<?php

namespace Drupal\digital_publication_issue;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Digital publication issue entity.
 *
 * @see \Drupal\digital_publication_issue\Entity\DigitalPublicationIssue.
 */
class DigitalPublicationIssueAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished digital publication issue entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published digital publication issue entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit digital publication issue entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete digital publication issue entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add digital publication issue entities');
  }

}
