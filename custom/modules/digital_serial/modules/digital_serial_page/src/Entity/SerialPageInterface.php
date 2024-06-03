<?php

namespace Drupal\digital_serial_page\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\digital_serial_issue\Entity\SerialIssueInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Serial page entities.
 *
 * @ingroup digital_serial_page
 */
interface SerialPageInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the active pager number.
   *
   * @return int
   *   Serial page viewer's active pager number.
   */
  public function getActivePagerNo();

  /**
   * Gets the Serial page number.
   *
   * @return string
   *   Number of the Serial page.
   */
  public function getPageNo();

  /**
   * Sets the Serial page number.
   *
   * @param string $page_no
   *   The Serial page number.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setPageNo($page_no);

  /**
   * Gets the Serial page sort string.
   *
   * @return string
   *   Sort String of the Serial page.
   */
  public function getPageSort();

  /**
   * Sets the Serial page number.
   *
   * @param string $page_sort
   *   The Serial page sort number.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setPageSort($page_sort);

  /**
   * Gets the styled image.
   *
   * @return array
   *   The render array of the styled image.
   */
  public function getStyledImage($image_style);

  /**
   * Gets the styled image linked to original.
   *
   * @return string
   *   The link object to the original image.
   */
  public function getLinkedStyledImage($image_style);

  /**
   * Gets the Serial page creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Serial page.
   */
  public function getCreatedTime();

  /**
   * Sets the Serial page creation timestamp.
   *
   * @param int $timestamp
   *   The Serial page creation timestamp.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Serial page published status indicator.
   *
   * Unpublished Serial page are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Serial page is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Serial page.
   *
   * @param bool $published
   *   TRUE to set this Serial page to published, FALSE to set unpublished.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setPublished($published);

  /**
   * Gets the parent issue for this page.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssueInterface
   *   The parent issue.
   */
  public function getParentIssue();

  /**
   * Sets the parent issue of a Serial page.
   *
   * @param \Drupal\digital_serial_issue\Entity\SerialIssueInterface $issue
   *   The issue to set as parent.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setParentIssue(SerialIssueInterface $issue);

  /**
   * Sets the parent title of a Serial page.
   *
   * @param int $issue_id
   *   The ID of the issue to set as parent.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setParentIssueById($issue_id);

  /**
   * Gets the Serial page OCR.
   *
   * @return string
   *   OCR content of the Serial page.
   */
  public function getPageOcr();

  /**
   * Sets the Serial page OCR.
   *
   * @param string $page_ocr
   *   The Serial page OCR content.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setPageOcr($page_ocr);

  /**
   * Gets the Serial page HOCR.
   *
   * @return string
   *   HOCR content of the Serial page.
   */
  public function getPageHocr();

  /**
   * Sets the Serial page HOCR.
   *
   * @param string $page_hocr
   *   The Serial page HOCR content.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setPageHocr($page_hocr);

  /**
   * Gets the storage URI for the Serial page image.
   *
   * @return string
   *  The storage URI for the Serial page image.
   */
  public function getPagePermImageStorageUri();

  /**
   * Moves the Serial page image to permanent storage.
   *
   * @param bool $move_file
   *   TRUE to move the file on-disk, FALSE to only update the database.
   */
  public function movePageImageToPermanentStorage($move_file = TRUE);

}
