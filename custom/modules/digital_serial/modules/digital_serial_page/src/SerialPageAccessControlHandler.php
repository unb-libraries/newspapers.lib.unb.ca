<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Serial page entity.
 *
 * @see \Drupal\digital_serial_page\Entity\SerialPage.
 */
class SerialPageAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_serial_page\Entity\SerialPageInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished serial page entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published serial page entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit serial page entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete serial page entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add serial page entities');
  }

}
