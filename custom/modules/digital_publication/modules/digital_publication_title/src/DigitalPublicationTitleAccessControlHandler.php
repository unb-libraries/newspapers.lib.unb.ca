<?php

namespace Drupal\digital_publication_title;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Digital publication title entity.
 *
 * @see \Drupal\digital_publication_title\Entity\DigitalPublicationTitle.
 */
class DigitalPublicationTitleAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\digital_publication_title\Entity\DigitalPublicationTitleInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished digital publication title entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published digital publication title entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit digital publication title entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete digital publication title entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add digital publication title entities');
  }

}
