<?php

namespace Drupal\panb_title_content\Event;

use Drupal\migrate\Row;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines the PANB relationships migrate event subscriber.
 */
class PanbTitleRelationshipsEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '1_b_panb_title_relationships';
  const SRC_FULL_TITLE_ABSORBED_BY_COLUMN = '18';
  const SRC_FULL_TITLE_CONTINUED_BY_COLUMN = '15';
  const SRC_FULL_TITLE_CONTINUES_FROM_COLUMN = '14';
  const SRC_FULL_TITLE_MERGED_WITH_COLUMN = '16';
  const SRC_FULL_TITLE_RECORD_FILE = '/app/html/modules/custom/panb_title_content/data/csv/z_panb_full_title_metadata.csv';
  const SRC_FULL_TITLE_RECORD_ID_COLUMN = '0';
  const SRC_FULL_TITLE_TO_FORM_COLUMN = '17';

  /**
   * The current row.
   *
   * @var \Drupal\migrate\Row
   */
  protected Row $curRow;

  /**
   * The current row's PANB id.
   *
   * @var string
   */
  protected string $curRowPanbId;

  /**
   * The current row's PANB full metadata.
   *
   * @var array
   */
  protected array $curRowFullMetadata;

  /**
   * The current row's node, previously migrated in 0_a_panb_title_content.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected Node $curRowTitleNode;

  /**
   * The current row's node, previously migrated in 0_a_panb_title_content.
   *
   * @var bool
   */
  protected bool $curRowTitleNodeChanged = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = ['onPrepareRow', 0];
    return $events;
  }

  /**
   * Reacts to a new migration row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   *
   * @throws \Exception
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) : void {
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    // Only act on rows for this migration.
    if ($migration_id == self::MIGRATION_ID) {
      $this->curRow = $event->getRow();
      $this->curRowPanbId = trim($this->curRow->getSourceProperty('ID'));
      $this->readRowPanbFullMetadata();
      $this->loadRowNode();
      if (!empty($this->curRowTitleNode)) {
        $this->setRowRelationships();
      }
    }
  }

  /**
   * Sets 'full' title metadata for the row from data provided by PANB.
   */
  protected function readRowPanbFullMetadata() : void {
    $full_metadata = array_map(
      'str_getcsv',
      file(self::SRC_FULL_TITLE_RECORD_FILE)
    );
    foreach ($full_metadata as $full_title_metadata) {
      if ($full_title_metadata[self::SRC_FULL_TITLE_RECORD_ID_COLUMN] == $this->curRowPanbId) {
        $this->curRowFullMetadata = $full_title_metadata;
        return;
      }
    }
  }

  /**
   * Sets the current row's previously imported node.
   */
  protected function loadRowNode() : void {
    unset($this->curRowTitleNode);
    $this->curRowTitleNodeChanged = FALSE;

    $nid = $this->getNidFromPanbId(
      $this->curRowPanbId
    );
    if (!empty($nid)) {
      $this->curRowTitleNode = Node::load($nid);
    }
  }

  /**
   * Returns the node ID of a publication matching the PANB ID.
   *
   * @return int
   *   The node ID of the publication.
   */
  protected function getNidFromPanbId($panb_id) : int {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'publication')
      ->condition('field_panb_id', $panb_id)
      // This is necessary, as the nodes are unpublished.
      ->accessCheck(FALSE);
    $matches = $query->execute();
    return reset($matches);
  }

  /**
   * Sets all the current row's entity relationships.
   */
  protected function setRowRelationships() {
    $this->setPreceedingRelationships();
    $this->setSucceedingRelationships();
    if ($this->curRowTitleNodeChanged) {
      $this->curRowTitleNode->save();
    }
  }

  /**
   * Sets the current row's preceeding entity relationships.
   */
  protected function setPreceedingRelationships() {
    $this->setContinuesFrom();
  }

  /**
   * Sets the current row's continuesFrom entity relationships.
   */
  protected function setContinuesFrom() {
    if (!$this->fullMetaDataColumnIsEmpty(self::SRC_FULL_TITLE_CONTINUES_FROM_COLUMN)) {
      $continues_from_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_CONTINUES_FROM_COLUMN);
      $continues_from_nids = $this->getNidsFromPanbIds($continues_from_panb_ids);
      if (!empty($continues_from_nids)) {
        $this->curRowTitleNode->set('field_serial_relation_pre_ref_up', $continues_from_nids);
        $this->curRowTitleNode->set('field_serial_relationship_op_pre', 'continues');
        $this->curRowTitleNodeChanged = TRUE;
      }
    }
  }

  /**
   * Determines if the full data set has meaningful data in a column.
   *
   * @param string $column_id
   *   The ID to query.
   *
   * @return bool
   *   TRUE if the column has meaningful data, FALSE otherwise.
   */
  protected function fullMetaDataColumnIsEmpty(string $column_id) : bool {
    if (empty($this->curRowFullMetadata[$column_id])) {
      return TRUE;
    }
    if (trim($this->curRowFullMetadata[$column_id]) == '-') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Retrieves data values from a comma separated column in the full data set.
   *
   * @param string $column_id
   *   The ID to query.
   *
   * @return array
   *   An array of values - multiple values if separated by a comma.
   */
  protected function getFullMetadataColumnCommaSeparatedValues(string $column_id) : array {
    return explode(',', $this->curRowFullMetadata[$column_id]);
  }

  /**
   * Returns the node IDs of publications matching the PANB IDs.
   *
   * @return array
   *   The node IDs of the publications.
   */
  protected function getNidsFromPanbIds(array $panb_ids) : array {
    $nids = [];
    foreach ($panb_ids as $panb_id) {
      $nids[] = $this->getNidFromPanbId($panb_id);
    }
    if (isset($nids[0]) && $nids[0] == 0) {
      unset($nids[0]);
    }
    return array_values($nids);
  }

  /**
   * Sets the row's succeeding entity relationships.
   */
  protected function setSucceedingRelationships() {
    $this->setContinuedBy();
    $this->setMergedWith();
    $this->setAbsorbedBy();
  }

  /**
   * Sets the current row's continuedBy entity relationships.
   */
  protected function setContinuedBy() {
    if (!$this->fullMetaDataColumnIsEmpty(self::SRC_FULL_TITLE_CONTINUED_BY_COLUMN)) {
      $continued_by_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_CONTINUED_BY_COLUMN);
      $continued_by_nids = $this->getNidsFromPanbIds($continued_by_panb_ids);
      if (!empty($continued_by_nids)) {
        $merged_with_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_MERGED_WITH_COLUMN);
        $merged_with_nids = $this->getNidsFromPanbIds($merged_with_panb_ids);
        // If this column has merged-with, we will use this as to-form instead.
        if (empty($merged_with_nids)) {
          $this->curRowTitleNode->set('field_serial_relation_suc_ref_dn', $continued_by_nids);
          $this->curRowTitleNode->set('field_serial_relationship_op_suc', 'continued_by');
          $this->curRowTitleNodeChanged = TRUE;
        }
      }
    }
  }

  /**
   * Sets the current row's mergedWith entity relationships.
   */
  protected function setMergedWith() {
    if (!$this->fullMetaDataColumnIsEmpty(self::SRC_FULL_TITLE_MERGED_WITH_COLUMN)) {
      $merged_with_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_MERGED_WITH_COLUMN);

      if (!$this->fullMetaDataColumnIsEmpty(self::SRC_FULL_TITLE_TO_FORM_COLUMN)) {
        $to_form_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_TO_FORM_COLUMN);
      }
      elseif (!$this->fullMetaDataColumnIsEmpty(self::SRC_FULL_TITLE_CONTINUED_BY_COLUMN)) {
        $to_form_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_CONTINUED_BY_COLUMN);
      }
      else {
        return;
      }

      // If either merged-with or to-form values empty, do nothing.
      if (!empty($merged_with_panb_ids) && !empty($to_form_panb_ids)) {
        $merged_with_nids = $this->getNidsFromPanbIds($merged_with_panb_ids);
        $to_form_nids = $this->getNidsFromPanbIds($to_form_panb_ids);
        // If either merged-with or to-form NIDs empty, do nothing.
        if (!empty($merged_with_nids) && !empty($to_form_nids)) {
          $this->curRowTitleNode->set('field_serial_relation_suc_ref_up', $merged_with_nids);
          $this->curRowTitleNode->set('field_serial_relation_suc_ref_dn', $to_form_nids);
          $this->curRowTitleNode->set('field_serial_relationship_op_suc', 'merged_with_form');
          $this->curRowTitleNodeChanged = TRUE;
        }
      }
    }
  }

  /**
   * Sets the current row's absorbedBy entity relationships.
   */
  protected function setAbsorbedBy() {
    if (!$this->fullMetaDataColumnIsEmpty(self::SRC_FULL_TITLE_ABSORBED_BY_COLUMN)) {
      $absorbed_by_panb_ids = $this->getFullMetadataColumnCommaSeparatedValues(self::SRC_FULL_TITLE_ABSORBED_BY_COLUMN);
      $absorbed_by_nids = $this->getNidsFromPanbIds($absorbed_by_panb_ids);
      if (!empty($absorbed_by_nids)) {
        $this->curRowTitleNode->set('field_serial_relation_suc_ref_dn', $absorbed_by_nids);
        $this->curRowTitleNode->set('field_serial_relationship_op_suc', 'absorbed_by');
        $this->curRowTitleNodeChanged = TRUE;
      }
    }
  }

}
