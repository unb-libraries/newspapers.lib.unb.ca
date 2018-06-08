<?php

namespace Drupal\digital_serial_issue\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\digital_serial_page\Entity\SerialPageInterface;

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

  /**
   * Gets the Serial issue title.
   *
   * @return string
   *   Title of the Serial issue.
   */
  public function getIssueTitle();

  /**
   * Sets the Serial issue title.
   *
   * @param string $issue_title
   *   The Serial issue title.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The called Serial issue entity.
   */
  public function setIssueTitle($issue_title);

  /**
   * Gets the Serial issue title.
   *
   * @return string
   *   Title of the Serial issue.
   */
  public function getIssueVol();

  /**
   * Sets the Serial issue volume.
   *
   * @param string $issue_vol
   *   The Serial issue volume.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The called Serial issue entity.
   */
  public function setIssueVol($issue_vol);

  /**
   * Gets the Serial issue Volume.
   *
   * @return string
   *   Volume of the Serial issue.
   */
  public function getIssueIssue();

  /**
   * Sets the Serial issue issue.
   *
   * @param string $issue_issue
   *   The Serial issue issue number.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The called Serial issue entity.
   */
  public function setIssueIssue($issue_issue);

  /**
   * Gets the Serial issue edition.
   *
   * @return string
   *   Edition of the Serial issue.
   */
  public function getIssueEdition();

  /**
   * Sets the Serial issue edition.
   *
   * @param string $issue_edition
   *   The Serial issue edition.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The called Serial issue entity.
   */
  public function setIssueEdition($issue_edition);

  /**
   * Check if a Page is associated with this Issue.
   *
   * @param \Drupal\digital_serial_page\SerialPageInterface $page
   *   The page.
   *
   * @return bool
   *   TRUE if the page is associated with this issue. FALSE otherwise.
   */
  public function hasPage(SerialPageInterface $page);

  /**
   * Set the pages associated with this issue.
   *
   * @param \Drupal\digital_serial_page\SerialPageInterface $page
   *   The page to add to the issue.
   *
   * @return $this
   */
  public function addPage(SerialPageInterface $page);

  /**
   * Gets the entity IDs of the pages associated with this issue.
   *
   * @return int[]
   *   An array of entity IDs.
   */
  public function getPageIds();

}
