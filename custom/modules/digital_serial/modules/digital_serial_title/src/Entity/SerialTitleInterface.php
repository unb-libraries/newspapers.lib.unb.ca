<?php

namespace Drupal\digital_serial_title\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital Serial Title entities.
 *
 * @ingroup digital_serial_title
 */
interface SerialTitleInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Digital Serial Title name.
   *
   * @return string
   *   Name of the Digital Serial Title.
   */
  public function getName();

  /**
   * Sets the Digital Serial Title name.
   *
   * @param string $name
   *   The Digital Serial Title name.
   *
   * @return \Drupal\digital_serial_title\Entity\SerialTitleInterface
   *   The called Digital Serial Title entity.
   */
  public function setName($name);

  /**
   * Gets the Digital Serial Title creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital Serial Title.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital Serial Title creation timestamp.
   *
   * @param int $timestamp
   *   The Digital Serial Title creation timestamp.
   *
   * @return \Drupal\digital_serial_title\Entity\SerialTitleInterface
   *   The called Digital Serial Title entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital Serial Title published status indicator.
   *
   * Unpublished Digital Serial Title are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital Serial Title is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital Serial Title.
   *
   * @param bool $published
   *   TRUE to set this Digital Serial Title to published, FALSE unpublished.
   *
   * @return \Drupal\digital_serial_title\Entity\SerialTitleInterface
   *   The called Digital Serial Title entity.
   */
  public function setPublished($published);

  /**
   * Returns the parent publication of the digital title.
   *
   * @return \Drupal\node\Entity\Node
   *   The parent publication if one exists.
   */
  public function getParentPublication();

}
