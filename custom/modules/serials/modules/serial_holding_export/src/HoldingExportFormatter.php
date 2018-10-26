<?php

namespace Drupal\serial_holding_export;

use Drupal\serial_holding\Entity\SerialHoldingInterface;
use Drupal\node\NodeInterface;

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
   * @param \Drupal\node\NodeInterface $publication
   *   The holding to use when generating the output.
   * @param array $config
   *   An associative array of configuration for this holding type.
   */
  protected function __construct(SerialHoldingInterface $holding, NodeInterface $publication, array $config) {
    $this->holding = $holding;
    $this->publication = $publication;
    $this->config = $config;
  }

  /**
   * Create a formatter object.
   *
   * @param \Drupal\serial_holding\Entity\SerialHoldingInterface $holding
   *   The holding to use when generating the output.
   * @param \Drupal\node\NodeInterface $publication
   *   The holding to use when generating the output.
   * @param array $config
   *   An associative array of configuration for this holding type.
   *
   * @return $this
   */
  public static function create(SerialHoldingInterface $holding, NodeInterface $publication, array $config) {
    return new static($holding, $publication, $config);
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
