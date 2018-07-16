<?php

namespace Drupal\visreg_content\Event;

use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\serial_holding\TaxonomyHelper;

/**
 * Defines the migrate event subscriber.
 */
class MicroformsTitleHoldingMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '1_visreg_content_microforms_holdings';

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

    // Only act on rows for this migration.
    if ($migration_id == self::MIGRATION_ID) {
      // Pass.
      $microfilm_holding_type_id = TaxonomyHelper::getHoldingTermId('Microfilm');
      $row->setSourceProperty('microfilm_holding_type_id', $microfilm_holding_type_id);

      // Parent publication_id.
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'publication')
        ->condition('field_previous_identifications', $row->getSourceProperty('uuid'));
      $nids = $query->execute();

      foreach ($nids as $nid) {
        $row->setSourceProperty('parent_publication_id', $nid);
      }

    }
  }

}
