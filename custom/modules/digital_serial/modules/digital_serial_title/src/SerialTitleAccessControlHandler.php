<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Digital Serial Title entity.
 *
 * @see \Drupal\digital_serial_title\Entity\SerialTitle.
 */
class SerialTitleAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_serial_title\Entity\SerialTitleInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished digital serial title entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published digital serial title entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit digital serial title entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete digital serial title entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add digital serial title entities');
  }

}
