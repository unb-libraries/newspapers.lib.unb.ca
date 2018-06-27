<?php

namespace Drupal\visreg_content\Event;

use Drupal\file\Entity\File;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines the migrate event subscriber.
 */
class PageMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '4_visreg_content_digital_pages';

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
      if (!empty($row->getSourceProperty('page_image'))) {
        $this->addFieldFile($row, 'page_image_object', $row->getSourceProperty('page_image'));
      }
      else {
        $row->setSourceProperty(
          'page_image_object',
          NULL
        );
      }
      if (!empty($row->getSourceProperty('page_ocr'))) {
        $this->addFieldFile($row, 'page_ocr_object', $row->getSourceProperty('page_ocr'));
      }
      else {
        $row->setSourceProperty(
          'page_ocr_object',
          NULL
        );
      }
      if (!empty($row->getSourceProperty('page_hocr'))) {
        $this->addFieldFile($row, 'page_hocr_object', $row->getSourceProperty('page_hocr'));
      }
      else {
        $row->setSourceProperty(
          'page_hocr_object',
          NULL
        );
      }
    }
  }

  /**
   * Add a file to the filesystem.
   *
   * @param object $row
   *   The current row from CSV being migrated.
   * @param string $field_map
   *   The destination mapping to the file field.
   * @param string $source
   *   The full path & filename of the source file.
   * @param string $destination
   *   The file storage destination. Defaults to public.
   *
   * @return bool
   *   Returns True if source file is found. False otherwise.
   */
  public function addFieldFile(&$row, $field_map, $source, $destination = 'public') {
    $file_basename = basename($source);
    $file_destination = "$destination://$file_basename";
    if (file_exists($source)) {
      $file_uri = file_unmanaged_copy($source, $file_destination,
        FILE_EXISTS_REPLACE);
      $file = File::Create([
        'uri' => $file_uri,
      ]);
      $row->setSourceProperty(
        $field_map,
        $file
      );
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
