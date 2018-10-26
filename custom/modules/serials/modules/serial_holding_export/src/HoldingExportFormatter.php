<?php

namespace Drupal\serial_holding_export;

use Drupal\serial_holding\Entity\SerialHoldingInterface;
use Drupal\node\NodeInterface;

/**
 * HoldingExportFormatter object.
 */
class HoldingExportFormatter {

  const BASE_URL = 'https://newspapers.lib.unb.ca';

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

  /**
   * Formatter callback for publication issn.
   */
  public function getPublicationIssn() {
    return $this->publication->get('field_issn')->getString();
  }

  /**
   * Formatter callback for holding start date.
   */
  public function getHoldingStartDate() {
    if (!empty($this->holding->get('holding_start_date')->date)) {
      return $this->formatHoldingDate(
        $this->holding->get('holding_start_date')->date
      );
    }
    return NULL;
  }

  /**
   * Formatter callback for holding end date.
   */
  public function getHoldingEndDate() {
    if (!empty($this->holding->get('holding_end_date')->date)) {
      return $this->formatHoldingDate(
        $this->holding->get('holding_end_date')->date
      );
    }
    return NULL;
  }

  /**
   * Format date strings.
   */
  private function formatHoldingDate($date) {
    return $date->format('Y-m-d');
  }

  /**
   * Formatter callback for publication URL.
   */
  public function getPublicationUrl() {
    return self::BASE_URL . $this->publication->toUrl()->toString();
  }

  /**
   * Formatter callback for coverage notes.
   */
  public function getCoverageNotes() {
    return $this->holding->getHoldingCoverage();
  }

  /**
   * Formatter callback for holding location.
   */
  public function getHoldingLocation() {
    return $this->holding->getHoldingLocation();
  }

  /**
   * Formatter callback for OCLC number.
   */
  public function getPublicationOclcNumber() {
    return $this->publication->get('field_oclc')->getString();
  }

}
