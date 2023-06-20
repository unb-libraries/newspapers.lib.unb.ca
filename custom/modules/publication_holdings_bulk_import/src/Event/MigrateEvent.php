<?php

namespace Drupal\publication_holdings_bulk_import\Event;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines the migrate event subscriber.
 */
class MigrateEvent implements EventSubscriberInterface {

  const HOLDING_TYPE_ID_MAP = [
    'Print' => 1,
    'Microform' => 2,
  ];

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
    $query = 'publication_holdings_import_standard_';

    // Only act on rows for this migration.
    if (substr($id, 0, strlen($query)) === $query) {
      $holding_start_date = trim($row->getSourceProperty(
        'Holding Start Date *'
      ));
      if ($holding_start_date == '0000-00-00') {
        $row->getSourceProperty('Holding Start Date *', NULL);
      }
      $holding_end_date = trim($row->getSourceProperty(
        'Holding End Date *'
      ));
      if ($holding_end_date == '0000-00-00') {
        $row->getSourceProperty('Holding End Date *', NULL);
      }
      $holding_type_string = trim($row->getSourceProperty('Type (Microform or Print) *'));
      if (
        !empty($holding_type_string) &&
        array_key_exists($holding_type_string, self::HOLDING_TYPE_ID_MAP)
      ) {
        $row->setSourceProperty(
          'holding_type_processed',
          self::HOLDING_TYPE_ID_MAP[$holding_type_string]
        );
        $microform_type_string = trim($row->getSourceProperty('Microform Type (neg or pos) *'));
        if ($holding_type_string == 'Microform' && !empty($microform_type_string)) {
          $row->setSourceProperty(
            'microform_type_processed',
            $microform_type_string
          );
        }
      }

    }
  }

}
