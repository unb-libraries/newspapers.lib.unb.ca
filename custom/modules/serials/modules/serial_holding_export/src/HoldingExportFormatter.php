<?php

namespace Drupal\serial_holding_export;

use Drupal\serial_holding\Entity\SerialHoldingInterface;

/**
 * HoldingExportFormatter object.
 */
class HoldingExportFormatter {

  /**
   * An associative array of configuration for this holding.
   *
   * @var array
   */
  protected $config = NULL;

  /**
   * The holding to use for the export functions.
   *
   * @var \Drupal\serial_holding\Entity\SerialHoldingInterface
   */
  protected $holding = NULL;

  /**
   * The publication to use for the export functions.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $publication = NULL;

  /**
   * Constructor.
   *
   * @param \Drupal\serial_holding\Entity\SerialHoldingInterface $holding
   *   The holding to use when generating the output.
   * @param array $config
   *   An associative array of configuration for this holding type.
   */
  protected function __construct(SerialHoldingInterface $holding, array $config) {
    $this->holding = $holding;
    $this->config = $config;
    $this->publication = $holding->getParentTitle();
  }

  /**
   * Create a formatter object.
   *
   * @param \Drupal\serial_holding\Entity\SerialHoldingInterface $holding
   *   The holding to use when generating the output.
   * @param array $config
   *   An associative array of configuration for this holding type.
   *
   * @return $this
   */
  public static function create(SerialHoldingInterface $holding, array $config) {
    return new static($holding, $config);
  }

  /**
   * Formatter callback for action.
   */
  public function getAction() {
    return $this->config['action'];
  }

  /**
   * Formatter callback for coverage depth.
   */
  public function getCoverageDepth() {
    return $this->config['coverage_depth'];
  }

  /**
   * Formatter callback for collection ID.
   */
  public function getOclcCollectionId() {
    return $this->config['collection_id'];
  }

  /**
   * Formatter callback for collection name.
   */
  public function getOclcCollectionName() {
    return $this->config['collection_name'];
  }

  /**
   * Formatter callback for publication title.
   */
  public function getPublicationTitle() {
    return $this->publication->getTitle();
  }

}
