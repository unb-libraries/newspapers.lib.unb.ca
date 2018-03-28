<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface;

/**
 * Defines the storage handler class for Digital serial issue entities.
 *
 * This extends the base storage class, adding required special handling for
 * Digital serial issue entities.
 *
 * @ingroup digital_serial_issue
 */
interface DigitalSerialIssueStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Digital serial issue revision IDs for a specific Digital serial issue.
   *
   * @param \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface $entity
   *   The Digital serial issue entity.
   *
   * @return int[]
   *   Digital serial issue revision IDs (in ascending order).
   */
  public function revisionIds(DigitalSerialIssueInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Digital serial issue author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Digital serial issue revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface $entity
   *   The Digital serial issue entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(DigitalSerialIssueInterface $entity);

  /**
   * Unsets the language for all Digital serial issue with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
