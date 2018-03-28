<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Digital serial page entity.
 *
 * @see \Drupal\digital_serial_page\Entity\DigitalSerialPage.
 */
class DigitalSerialPageAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished digital serial page entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published digital serial page entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit digital serial page entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete digital serial page entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add digital serial page entities');
  }

}
