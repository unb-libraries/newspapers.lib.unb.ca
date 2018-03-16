<?php

namespace Drupal\serial_holding\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Serial holding entities.
 *
 * @ingroup serial_holding
 */
interface SerialHoldingInterface extends  ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Serial holding name.
   *
   * @return string
   *   Name of the Serial holding.
   */
  public function getName();

  /**
   * Sets the Serial holding name.
   *
   * @param string $name
   *   The Serial holding name.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setName($name);

  /**
   * Gets the Serial holding creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Serial holding.
   */
  public function getCreatedTime();

  /**
   * Sets the Serial holding creation timestamp.
   *
   * @param int $timestamp
   *   The Serial holding creation timestamp.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Serial holding published status indicator.
   *
   * Unpublished Serial holding are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Serial holding is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Serial holding.
   *
   * @param bool $published
   *   TRUE to set this Serial holding to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setPublished($published);

}
