<?php

namespace Drupal\digital_publication_issue;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface;

/**
 * Defines the storage handler class for Digital publication issue entities.
 *
 * This extends the base storage class, adding required special handling for
 * Digital publication issue entities.
 *
 * @ingroup digital_publication_issue
 */
class DigitalPublicationIssueStorage extends SqlContentEntityStorage implements DigitalPublicationIssueStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(DigitalPublicationIssueInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {digital_publication_issue_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {digital_publication_issue_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(DigitalPublicationIssueInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {digital_publication_issue_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('digital_publication_issue_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
