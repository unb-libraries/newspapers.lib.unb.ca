<?php

namespace Drupal\instance_initial_content\Event;

use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\serial_holding\Entity\SerialHolding;
use Drupal\serial_holding\TaxonomyHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines the migrate event subscriber.
 */
class DigitalTitleMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '2_instance_initial_content_digital_titles';

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
      $digital_id = TaxonomyHelper::getHoldingTermId('Digital');
      $entity_values = [
        'holding_type' => $digital_id,
        'holding_coverage' => 'Digital Issues at UNB Libraries',
        'user_id' => \Drupal::currentUser()->id(),
        'status' => 1,
        'parent_title' => 1035,
        'holding_digital_title' => 1,
      ];
      $digital_holding = SerialHolding::create($entity_values);
      $digital_holding->save();
    }
  }

}
