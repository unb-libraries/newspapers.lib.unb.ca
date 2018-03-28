<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface DigitalSerialPageStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Digital serial page revision IDs for a specific Digital serial page.
   *
   * @param \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface $entity
   *   The Digital serial page entity.
   *
   * @return int[]
   *   Digital serial page revision IDs (in ascending order).
   */
  public function revisionIds(DigitalSerialPageInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Digital serial page author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Digital serial page revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface $entity
   *   The Digital serial page entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(DigitalSerialPageInterface $entity);

  /**
   * Unsets the language for all Digital serial page with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
