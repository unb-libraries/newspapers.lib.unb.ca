<?php

namespace Drupal\serial_holding\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Serial holding entities.
 *
 * @ingroup serial_holding
 */
interface SerialHoldingInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

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
   *   TRUE to set this holding to published, FALSE to set as unpublished.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setPublished($published);

  /**
   * Returns the parent title of the Serial Holding.
   *
   * @return \Drupal\node\NodeInterface
   *   The parent title if one exists.
   */
  public function getParentTitle();

  /**
   * Sets the parent title of a Serial holding.
   *
   * @param \Drupal\node\NodeInterface $title
   *   The title to set as parent.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setParentTitle(NodeInterface $title);

  /**
   * Returns the holding type of the Serial Holding.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The holding type.
   */
  public function getHoldingType();

  /**
   * Sets the holding type of a Serial holding.
   *
   * @param \Drupal\taxonomy\TermInterface $type
   *   The holding type to set.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setHoldingType(TermInterface $type);

  /**
   * Gets the Serial holding start date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Object of the holding start date the Serial holding.
   */
  public function getHoldingStartDate();

  /**
   * Sets the Serial holding holding start date.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The Serial holding start date.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The Serial holding entity.
   */
  public function setHoldingStartDate(DrupalDateTime $date);

  /**
   * Gets the Serial holding end date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Object of the holding end date the Serial holding.
   */
  public function getHoldingEndDate();

  /**
   * Sets the Serial holding holding end date.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The Serial holding end date.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The Serial holding entity.
   */
  public function setHoldingEndDate(DrupalDateTime $date);

  /**
   * Gets the Serial holding coverage statement.
   *
   * @return string
   *   The coverage statement of the Serial holding.
   */
  public function getHoldingCoverage();

  /**
   * Sets the Serial holding coverage statement.
   *
   * @param string $coverage
   *   The Serial holding coverage statement.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setHoldingCoverage($coverage);

  /**
   * Gets the Serial holding location.
   *
   * @return string
   *   The location of the Serial holding.
   */
  public function getHoldingLocation();

  /**
   * Sets the Serial holding location.
   *
   * @param string $location
   *   The Serial holding location.
   *
   * @return \Drupal\serial_holding\Entity\SerialHoldingInterface
   *   The called Serial holding entity.
   */
  public function setHoldingLocation($location);

}
