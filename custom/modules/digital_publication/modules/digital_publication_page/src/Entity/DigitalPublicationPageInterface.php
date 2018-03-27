<?php

namespace Drupal\digital_publication_page\Entity;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital publication page entities.
 *
 * @ingroup digital_publication_page
 */
interface DigitalPublicationPageInterface extends RevisionableInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Digital publication page name.
   *
   * @return string
   *   Name of the Digital publication page.
   */
  public function getName();

  /**
   * Sets the Digital publication page name.
   *
   * @param string $name
   *   The Digital publication page name.
   *
   * @return \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface
   *   The called Digital publication page entity.
   */
  public function setName($name);

  /**
   * Gets the Digital publication page creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital publication page.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital publication page creation timestamp.
   *
   * @param int $timestamp
   *   The Digital publication page creation timestamp.
   *
   * @return \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface
   *   The called Digital publication page entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital publication page published status indicator.
   *
   * Unpublished Digital publication page are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital publication page is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital publication page.
   *
   * @param bool $published
   *   TRUE to set this Digital publication page to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface
   *   The called Digital publication page entity.
   */
  public function setPublished($published);

  /**
   * Gets the Digital publication page revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Digital publication page revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface
   *   The called Digital publication page entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Digital publication page revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Digital publication page revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\digital_publication_page\Entity\DigitalPublicationPageInterface
   *   The called Digital publication page entity.
   */
  public function setRevisionUserId($uid);

}
