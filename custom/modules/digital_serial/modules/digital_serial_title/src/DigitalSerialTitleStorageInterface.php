<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface DigitalSerialTitleStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Digital serial title revision IDs for a specific Digital serial title.
   *
   * @param \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface $entity
   *   The Digital serial title entity.
   *
   * @return int[]
   *   Digital serial title revision IDs (in ascending order).
   */
  public function revisionIds(DigitalSerialTitleInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Digital serial title author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Digital serial title revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface $entity
   *   The Digital serial title entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(DigitalSerialTitleInterface $entity);

  /**
   * Unsets the language for all Digital serial title with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
