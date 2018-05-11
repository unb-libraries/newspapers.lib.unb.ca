<?php

namespace Drupal\digital_serial_page\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Serial page entities.
 *
 * @ingroup digital_serial_page
 */
interface SerialPageInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

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

}
