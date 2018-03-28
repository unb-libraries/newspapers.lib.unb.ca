<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Digital serial issue entity.
 *
 * @see \Drupal\digital_serial_issue\Entity\DigitalSerialIssue.
 */
class DigitalSerialIssueAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished digital serial issue entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published digital serial issue entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit digital serial issue entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete digital serial issue entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add digital serial issue entities');
  }

}
