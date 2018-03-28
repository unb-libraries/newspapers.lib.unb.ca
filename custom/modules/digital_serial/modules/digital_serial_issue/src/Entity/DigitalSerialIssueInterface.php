<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital serial issue entities.
 *
 * @ingroup digital_serial_issue
 */
interface DigitalSerialIssueInterface extends RevisionableInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Digital serial issue name.
   *
   * @return string
   *   Name of the Digital serial issue.
   */
  public function getName();

  /**
   * Sets the Digital serial issue name.
   *
   * @param string $name
   *   The Digital serial issue name.
   *
   * @return \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface
   *   The called Digital serial issue entity.
   */
  public function setName($name);

  /**
   * Gets the Digital serial issue creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital serial issue.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital serial issue creation timestamp.
   *
   * @param int $timestamp
   *   The Digital serial issue creation timestamp.
   *
   * @return \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface
   *   The called Digital serial issue entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital serial issue published status indicator.
   *
   * Unpublished Digital serial issue are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital serial issue is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital serial issue.
   *
   * @param bool $published
   *   TRUE to set this Digital serial issue to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface
   *   The called Digital serial issue entity.
   */
  public function setPublished($published);

  /**
   * Gets the Digital serial issue revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Digital serial issue revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface
   *   The called Digital serial issue entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Digital serial issue revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Digital serial issue revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface
   *   The called Digital serial issue entity.
   */
  public function setRevisionUserId($uid);

}
