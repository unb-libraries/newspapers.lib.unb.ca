<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\digital_serial_page\Entity\DigitalSerialPageInterface;

/**
 * Defines the storage handler class for Digital serial page entities.
 *
 * This extends the base storage class, adding required special handling for
 * Digital serial page entities.
 *
 * @ingroup digital_serial_page
 */
class DigitalSerialPageStorage extends SqlContentEntityStorage implements DigitalSerialPageStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(DigitalSerialPageInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {digital_serial_page_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {digital_serial_page_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(DigitalSerialPageInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {digital_serial_page_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('digital_serial_page_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
