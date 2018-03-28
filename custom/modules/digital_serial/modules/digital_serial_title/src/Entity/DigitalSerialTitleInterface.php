<?php

namespace Drupal\digital_serial_title\Entity;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital serial title entities.
 *
 * @ingroup digital_serial_title
 */
interface DigitalSerialTitleInterface extends RevisionableInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Digital serial title name.
   *
   * @return string
   *   Name of the Digital serial title.
   */
  public function getName();

  /**
   * Sets the Digital serial title name.
   *
   * @param string $name
   *   The Digital serial title name.
   *
   * @return \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface
   *   The called Digital serial title entity.
   */
  public function setName($name);

  /**
   * Gets the Digital serial title creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital serial title.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital serial title creation timestamp.
   *
   * @param int $timestamp
   *   The Digital serial title creation timestamp.
   *
   * @return \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface
   *   The called Digital serial title entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital serial title published status indicator.
   *
   * Unpublished Digital serial title are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital serial title is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital serial title.
   *
   * @param bool $published
   *   TRUE to set this Digital serial title to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface
   *   The called Digital serial title entity.
   */
  public function setPublished($published);

  /**
   * Gets the Digital serial title revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Digital serial title revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface
   *   The called Digital serial title entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Digital serial title revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Digital serial title revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface
   *   The called Digital serial title entity.
   */
  public function setRevisionUserId($uid);

}
