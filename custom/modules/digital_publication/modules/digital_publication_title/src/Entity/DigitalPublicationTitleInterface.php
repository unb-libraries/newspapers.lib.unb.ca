<?php

namespace Drupal\digital_publication_title\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Digital publication title entities.
 *
 * @ingroup digital_publication_title
 */
interface DigitalPublicationTitleInterface extends  ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Digital publication title name.
   *
   * @return string
   *   Name of the Digital publication title.
   */
  public function getName();

  /**
   * Sets the Digital publication title name.
   *
   * @param string $name
   *   The Digital publication title name.
   *
   * @return \Drupal\digital_publication_title\Entity\DigitalPublicationTitleInterface
   *   The called Digital publication title entity.
   */
  public function setName($name);

  /**
   * Gets the Digital publication title creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Digital publication title.
   */
  public function getCreatedTime();

  /**
   * Sets the Digital publication title creation timestamp.
   *
   * @param int $timestamp
   *   The Digital publication title creation timestamp.
   *
   * @return \Drupal\digital_publication_title\Entity\DigitalPublicationTitleInterface
   *   The called Digital publication title entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Digital publication title published status indicator.
   *
   * Unpublished Digital publication title are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Digital publication title is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Digital publication title.
   *
   * @param bool $published
   *   TRUE to set this Digital publication title to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\digital_publication_title\Entity\DigitalPublicationTitleInterface
   *   The called Digital publication title entity.
   */
  public function setPublished($published);

}
