<?php

namespace Drupal\visreg_content\Event;

use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\node\Entity\Node;

/**
 * Defines the migrate event subscriber.
 */
class MicroformsTitleSucMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '1_visreg_content_microforms_suc';
  const PUBLICATION_NODE_TYPE = 'publication';
  const PUBLICATION_OLD_ID_FIELD = 'field_previous_identifications';
  const SUCC_OP_FIELD_NAME = 'field_serial_relationship_op_suc';
  const SUCC_UPSTREAM_FIELD_NAME = 'field_serial_relation_suc_ref_up';
  const SUCC_DOWNSTREAM_FIELD_NAME = 'field_serial_relation_suc_ref_dn';

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
   *
   * @throws \Exception
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    $succeding_codes = [
      '0' => 'continued_by',
      '4' => 'absorbed_by',
      '6' => 'split_into',
      '7' => 'merged_with_form',
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

      foreach ($nids as $nid) {
        $pub_node = Node::load($nid);
        $pub_node->set(self::SUCC_OP_FIELD_NAME, $succeding_codes[$relationship_type]);

        // Merged is the only type that uses the downstream field.
        switch ($relationship_type) {
          case '7':
            $pub_node->set(self::SUCC_UPSTREAM_FIELD_NAME, [$targets[0]]);
            $pub_node->set(self::SUCC_DOWNSTREAM_FIELD_NAME, [$targets[1]]);
            break;

          default:
            $pub_node->set(self::SUCC_DOWNSTREAM_FIELD_NAME, $targets);
            break;
        }

        $pub_node->save();
      }
    }
  }

}
