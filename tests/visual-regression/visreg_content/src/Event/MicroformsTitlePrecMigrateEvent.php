<?php

namespace Drupal\visreg_content\Event;

use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\node\Entity\Node;

/**
 * Defines the migrate event subscriber.
 */
class MicroformsTitlePrecMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '1_visreg_content_microforms_prec';
  const PUBLICATION_NODE_TYPE = 'publication';
  const PUBLICATION_OLD_ID_FIELD = 'field_previous_identifications';
  const PRECEDING_OP_FIELD_NAME = 'field_serial_relationship_op_pre';
  const PRECEDING_UPSTREAM_FIELD_NAME = 'field_serial_relation_pre_ref_up';
  const PRECEDING_DOWNSTREAM_FIELD_NAME = 'field_serial_relation_pre_ref_dn';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = ['onPrepareRow', 0];
    return $events;
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    $preceding_codes = [
      '0' => 'continues',
      '4' => 'union',
      '5' => 'absorbed',
      '7' => 'separated',
    ];

    // Only act on rows for this migration.
    if ($migration_id == self::MIGRATION_ID) {
      $relationship_type = $row->getSourceProperty('relationship');
      $targets = explode('|', $row->getSourceProperty('target_id'));
      $old_pub_id = $row->getSourceProperty('title_id');

      // Parent publication_id.
      $query = \Drupal::entityQuery('node')
        ->condition('type', self::PUBLICATION_NODE_TYPE)
        ->condition(self::PUBLICATION_OLD_ID_FIELD, $old_pub_id);
      $nids = $query->execute();

      foreach ($nids as $source_nid) {
        $pub_node = Node::load($source_nid);
        $pub_node->set(self::PRECEDING_OP_FIELD_NAME, $preceding_codes[$relationship_type]);

        // Get current target NIDs.
        $targets_query = \Drupal::entityQuery('node')
          ->condition('type', self::PUBLICATION_NODE_TYPE)
          ->condition(self::PUBLICATION_OLD_ID_FIELD, $targets, 'IN');
        $target_nids = $targets_query->execute();

        $pub_node->set(self::PRECEDING_UPSTREAM_FIELD_NAME, $target_nids);
        $pub_node->save();
      }
    }
  }

}
