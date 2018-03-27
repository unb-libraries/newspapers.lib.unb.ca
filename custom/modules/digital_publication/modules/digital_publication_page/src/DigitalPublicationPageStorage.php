<?php

namespace Drupal\digital_publication_page;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface;

/**
 * Defines the storage handler class for Digital publication page entities.
 *
 * This extends the base storage class, adding required special handling for
 * Digital publication page entities.
 *
 * @ingroup digital_publication_page
 */
class DigitalPublicationPageStorage extends SqlContentEntityStorage implements DigitalPublicationPageStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(DigitalPublicationPageInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {digital_publication_page_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {digital_publication_page_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(DigitalPublicationPageInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {digital_publication_page_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('digital_publication_page_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
