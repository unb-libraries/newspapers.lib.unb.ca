<?php

namespace Drupal\digital_publication_issue;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface DigitalPublicationIssueStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Digital publication issue revision IDs for a specific Digital publication issue.
   *
   * @param \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface $entity
   *   The Digital publication issue entity.
   *
   * @return int[]
   *   Digital publication issue revision IDs (in ascending order).
   */
  public function revisionIds(DigitalPublicationIssueInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Digital publication issue author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Digital publication issue revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface $entity
   *   The Digital publication issue entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(DigitalPublicationIssueInterface $entity);

  /**
   * Unsets the language for all Digital publication issue with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
