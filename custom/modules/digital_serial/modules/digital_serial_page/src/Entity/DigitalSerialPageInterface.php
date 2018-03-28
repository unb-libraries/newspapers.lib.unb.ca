<?php

namespace Drupal\digital_serial_page\Entity;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital serial page entities.
 *
 * @ingroup digital_serial_page
 */
interface DigitalSerialPageInterface extends RevisionableInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Digital serial page name.
   *
   * @return string
   *   Name of the Digital serial page.
   */
  public function getName();

  /**
   * Sets the Digital serial page name.
   *
   * @param string $name
   *   The Digital serial page name.
   *
   * @return \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface
   *   The called Digital serial page entity.
   */
  public function setName($name);

  /**
   * Gets the Digital serial page creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital serial page.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital serial page creation timestamp.
   *
   * @param int $timestamp
   *   The Digital serial page creation timestamp.
   *
   * @return \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface
   *   The called Digital serial page entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital serial page published status indicator.
   *
   * Unpublished Digital serial page are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital serial page is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital serial page.
   *
   * @param bool $published
   *   TRUE to set this Digital serial page to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface
   *   The called Digital serial page entity.
   */
  public function setPublished($published);

  /**
   * Gets the Digital serial page revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Digital serial page revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface
   *   The called Digital serial page entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Digital serial page revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Digital serial page revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\digital_serial_page\Entity\DigitalSerialPageInterface
   *   The called Digital serial page entity.
   */
  public function setRevisionUserId($uid);

}
