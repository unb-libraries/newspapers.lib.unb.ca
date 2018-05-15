<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Serial issue entities.
 *
 * @ingroup digital_serial_issue
 */
interface SerialIssueInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Serial issue creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Serial issue.
   */
  public function getCreatedTime();

  /**
   * Sets the Serial issue creation timestamp.
   *
   * @param int $timestamp
   *   The Serial issue creation timestamp.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The called Serial issue entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Serial issue published status indicator.
   *
   * Unpublished Serial issue are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Serial issue is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Serial issue.
   *
   * @param bool $published
   *   TRUE to set this Serial issue to published, FALSE to set it unpublished.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The called Serial issue entity.
   */
  public function setPublished($published);

}
