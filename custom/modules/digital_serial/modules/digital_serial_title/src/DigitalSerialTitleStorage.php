<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface;

/**
 * Defines the storage handler class for Digital serial title entities.
 *
 * This extends the base storage class, adding required special handling for
 * Digital serial title entities.
 *
 * @ingroup digital_serial_title
 */
class DigitalSerialTitleStorage extends SqlContentEntityStorage implements DigitalSerialTitleStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(DigitalSerialTitleInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {digital_serial_title_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {digital_serial_title_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(DigitalSerialTitleInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {digital_serial_title_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('digital_serial_title_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
