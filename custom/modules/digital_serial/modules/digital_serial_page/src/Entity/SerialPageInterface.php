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
   * Gets the Serial page name.
   *
   * @return string
   *   Name of the Serial page.
   */
  public function getName();

  /**
   * Sets the Serial page name.
   *
   * @param string $name
   *   The Serial page name.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPageInterface
   *   The called Serial page entity.
   */
  public function setName($name);

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
