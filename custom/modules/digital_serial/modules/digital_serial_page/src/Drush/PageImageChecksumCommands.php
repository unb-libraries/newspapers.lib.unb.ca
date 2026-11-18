<?php

namespace Drupal\digital_serial_page\Drush;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\digital_serial_page\PageImageChecksum;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for page image checksums.
 */
class PageImageChecksumCommands extends DrushCommands {

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
  ) {
    parent::__construct();
  }

  /**
   * Fills image_sha256 for pages that do not have it.
   *
   * Writes the column directly rather than saving each page: a page save
   * reindexes its issue in Solr and may save its title, which for every page
   * on the site would take days. Only the checksum changes, so nothing else
   * needs to react. Safe to stop and run again; it resumes with the pages
   * still empty.
   *
   * @param array $options
   *   The command options.
   *
   * @option batch
   *   Pages to read and update per database query.
   * @option limit
   *   Stop after this many pages; 0 for no limit.
   *
   * @command digital-serial-page:backfill-image-sha256
   *
   * @usage drush digital-serial-page:backfill-image-sha256 --limit=1000
   *   Checksum the images of 1000 pages that have none.
   */
  public function backfillImageSha256(array $options = ['batch' => 500, 'limit' => 0]): void {
    $batch = max(1, (int) $options['batch']);
    $limit = max(0, (int) $options['limit']);

    $storage = $this->entityTypeManager->getStorage('digital_serial_page');
    $mapping = $storage->getTableMapping();
    $definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('digital_serial_page');
    $table = $mapping->getFieldTableName(PageImageChecksum::FIELD);
    $checksum_column = $mapping->getFieldColumnName($definitions[PageImageChecksum::FIELD], 'value');
    $image_column = $mapping->getFieldColumnName($definitions['page_image'], 'target_id');

    $done = 0;
    $unreadable = 0;
    $last_id = 0;
    while ($limit === 0 || $done + $unreadable < $limit) {
      $take = $limit === 0 ? $batch : min($batch, $limit - $done - $unreadable);
      $query = $this->database->select($table, 'p');
      $query->join('file_managed', 'f', "f.fid = p.$image_column");
      $rows = $query
        ->fields('p', ['id'])
        ->fields('f', ['uri'])
        ->isNull("p.$checksum_column")
        ->condition('p.id', $last_id, '>')
        ->orderBy('p.id')
        ->range(0, $take)
        ->execute()
        ->fetchAllKeyed();
      if (empty($rows)) {
        break;
      }

      $updated_ids = [];
      foreach ($rows as $id => $uri) {
        $last_id = (int) $id;
        $path = $this->fileSystem->realpath($uri);
        $checksum = $path && is_readable($path) ? hash_file(PageImageChecksum::ALGORITHM, $path) : FALSE;
        if ($checksum === FALSE) {
          $unreadable++;
          $this->logger()->warning("Page $id: cannot read $uri");
          continue;
        }
        $this->database->update($table)
          ->fields([$checksum_column => $checksum])
          ->condition('id', $id)
          ->execute();
        $updated_ids[] = (int) $id;
        $done++;
      }
      // Loaded pages may be cached without the new value.
      $storage->resetCache($updated_ids);
      $this->logger()->notice("Checksummed $done pages so far, up to page ID $last_id.");
    }

    $this->logger()->success("Checksummed $done pages; $unreadable images could not be read.");
  }

}
