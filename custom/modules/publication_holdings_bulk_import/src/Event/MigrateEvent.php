<?php

namespace Drupal\publication_holdings_bulk_import\Event;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Row;

/**
 * Defines the migrate event subscriber.
 */
class MigrateEvent implements EventSubscriberInterface {

  const MULTIPLE_VALUE_DELIMITER = '|';

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
    $id = $migration->id();
    $query = 'publication_holdings_bulk_import_standard';

    // Only act on rows for this migration.
    if (substr($id, 0, strlen($query)) === $query) {
      // Pass.
    }
  }

}
