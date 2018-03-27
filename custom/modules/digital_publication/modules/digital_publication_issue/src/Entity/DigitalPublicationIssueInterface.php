<?php

namespace Drupal\digital_publication_issue\Entity;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital publication issue entities.
 *
 * @ingroup digital_publication_issue
 */
interface DigitalPublicationIssueInterface extends RevisionableInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Digital publication issue name.
   *
   * @return string
   *   Name of the Digital publication issue.
   */
  public function getName();

  /**
   * Sets the Digital publication issue name.
   *
   * @param string $name
   *   The Digital publication issue name.
   *
   * @return \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface
   *   The called Digital publication issue entity.
   */
  public function setName($name);

  /**
   * Gets the Digital publication issue creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital publication issue.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital publication issue creation timestamp.
   *
   * @param int $timestamp
   *   The Digital publication issue creation timestamp.
   *
   * @return \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface
   *   The called Digital publication issue entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital publication issue published status indicator.
   *
   * Unpublished Digital publication issue are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital publication issue is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital publication issue.
   *
   * @param bool $published
   *   TRUE to set this Digital publication issue to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface
   *   The called Digital publication issue entity.
   */
  public function setPublished($published);

  /**
   * Gets the Digital publication issue revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Digital publication issue revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface
   *   The called Digital publication issue entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Digital publication issue revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Digital publication issue revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface
   *   The called Digital publication issue entity.
   */
  public function setRevisionUserId($uid);

}
