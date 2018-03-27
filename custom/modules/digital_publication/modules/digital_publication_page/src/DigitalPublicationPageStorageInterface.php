<?php

namespace Drupal\digital_publication_page;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface DigitalPublicationPageStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Digital publication page revision IDs for a specific Digital publication page.
   *
   * @param \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface $entity
   *   The Digital publication page entity.
   *
   * @return int[]
   *   Digital publication page revision IDs (in ascending order).
   */
  public function revisionIds(DigitalPublicationPageInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Digital publication page author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Digital publication page revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface $entity
   *   The Digital publication page entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(DigitalPublicationPageInterface $entity);

  /**
   * Unsets the language for all Digital publication page with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
