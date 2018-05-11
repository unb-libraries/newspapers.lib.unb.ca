<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Serial issue entity.
 *
 * @see \Drupal\digital_serial_issue\Entity\SerialIssue.
 */
class SerialIssueAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_serial_issue\Entity\SerialIssueInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished serial issue entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published serial issue entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit serial issue entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete serial issue entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add serial issue entities');
  }

}
