<?php

namespace Drupal\publication_holdings_bulk_import;

use Drupal\file\Entity\File;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use ForceUTF8\Encoding;
use Symfony\Component\Yaml\Yaml;

/**
 * PublicationHoldingsCsvMigration object.
 */
class PublicationHoldingsCsvMigration {

  /**
   * The error message of the migration.
   *
   * @var string
   */
  public $errors = [];

  /**
   * The ID of the current import.
   *
   * @var string
   */
  public $importId;

  /**
   * The file path to the configuration file.
   *
   * @var string
   */
  public $migrateFilePath;

  /**
   * Constructor.
   *
   * @param string $import_id
   *   The import ID of the migration.
   * @param string $import_file
   *   The path to the csv import file.
   * @param int $limit
   *   The limit of items to import.
   */
  public function __construct($import_id = NULL, $import_file = NULL, $limit = 1) {
    $time_obj = new \DateTime();
    $date_time_string = $time_obj->format('Y-m-d H:i:s');
    $date_time_stamp = $time_obj->getTimestamp();
    $this->importId = "{$import_id}_{$date_time_stamp}";

    if (!file_exists($import_file)) {
      $this->addError(
        t('Import file not found.')
      );
      return;
    }

    $module_handler = \Drupal::service('module_handler');
    $module_relative_path = $module_handler->getModule('publication_holdings_bulk_import')->getPath();
    $this->migrateFilePath = DRUPAL_ROOT . "/$module_relative_path/config/imports/$import_id.migration.migrate_csv.yml";

    if (!file_exists($this->migrateFilePath)) {
      $this->addError(
        t('Migration configuration file not found.')
      );
      return;
    }

    $config_contents = file_get_contents($this->migrateFilePath);
    $config_array = Yaml::parse($config_contents);

    $config_array['id'] = $this->importId;
    $config_array['label'] = "Herbarium Sample Import from $date_time_string";
    $config_array['source']['path'] = $import_file;

    $import_content = file_get_contents($import_file);
    $cleaned_content = self::cleanCsv($import_content);

    file_put_contents($import_file, $cleaned_content);

    $config_storage = \Drupal::service('config.storage');
    $config_storage->write('migrate_plus.migration.' . $this->importId, $config_array);
  }

  /**
   * Add an error to the migration.
   *
   * @param string $message
   *   The message to add.
   */
  private function addError($message) {
    $this->errors[] = $message;
  }

  /**
   * Correct common issues with uploaded CSV files.
   *
   * @param string $content
   *   The CSV file contents to clean.
   *
   * @return string
   *   The cleaned content.
   */
  private static function cleanCsv($content) {
    // Normalize Windows/OSX/Excel newlines.
    $content = preg_replace('~(*BSR_ANYCRLF)\R~', "\n", $content);

    // Fix Mangled UTF8 characters.
    $content = Encoding::fixUTF8($content);

    return $content;
  }

  /**
   * Run the CSV migration in from a batch.
   *
   * @param string $migration_id
   *   The migration ID to run.
   * @param int $item_limit
   *   The item limit.
   * @param mixed $context
   *   The batch context array.
   */
  public static function runCsvMigrationBatch($migration_id, $item_limit, &$context) {
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
    $executable = new MigrateExecutable($migration, new MigrateMessage(), ['limit' => $item_limit]);
    $executable->import();
  }

}
