<?php

namespace Drupal\serial_holding;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Serial holding entity.
 *
 * @see \Drupal\serial_holding\Entity\SerialHolding.
 */
class SerialHoldingAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\serial_holding\Entity\SerialHoldingInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished serial holding entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published serial holding entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit serial holding entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete serial holding entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add serial holding entities');
  }

}
