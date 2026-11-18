<?php

namespace Drupal\digital_serial_page;

use Drupal\digital_serial_page\Entity\SerialPageInterface;
use Drupal\file\FileInterface;

/**
 * Keeps a page's image_sha256 field equal to the checksum of its image file.
 *
 * Ingest compares this value with the checksum of its local copy, so it is
 * always computed here from the stored file and never taken from a client.
 */
final class PageImageChecksum {

  /**
   * The hash algorithm, as named by hash_file().
   */
  public const ALGORITHM = 'sha256';

  /**
   * The field holding the checksum.
   */
  public const FIELD = 'image_sha256';

  /**
   * Determines whether the page's stored checksum may be out of date.
   *
   * Pages saved without a new image keep their checksum, so the many saves
   * that only touch metadata or page_sort do not read the image again. A
   * changed checksum value with an unchanged image means a client tried to
   * set it, so it is recomputed as well.
   *
   * @param \Drupal\digital_serial_page\Entity\SerialPageInterface $page
   *   The page being saved.
   *
   * @return bool
   *   TRUE if the checksum must be computed.
   */
  public static function needsRefresh(SerialPageInterface $page): bool {
    $original = $page->getOriginal();
    if ($page->isNew() || $original === NULL) {
      return TRUE;
    }
    return $page->get('page_image')->target_id != $original->get('page_image')->target_id
      || $page->get(self::FIELD)->value !== $original->get(self::FIELD)->value;
  }

  /**
   * Sets the page's checksum from its image file, if it may be out of date.
   *
   * @param \Drupal\digital_serial_page\Entity\SerialPageInterface $page
   *   The page being saved.
   */
  public static function refresh(SerialPageInterface $page): void {
    if (!self::needsRefresh($page)) {
      return;
    }
    $file = $page->get('page_image')->entity;
    $checksum = $file instanceof FileInterface ? self::compute($file) : NULL;
    if ($checksum === NULL) {
      \Drupal::logger('digital_serial_page')->warning('Could not read the image of page @id to checksum it.', [
        '@id' => $page->id() ?? 'new',
      ]);
    }
    $page->set(self::FIELD, $checksum);
  }

  /**
   * Computes the checksum of a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return string|null
   *   The lowercase hex checksum, or NULL if the file cannot be read.
   */
  public static function compute(FileInterface $file): ?string {
    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (!$path || !is_readable($path)) {
      return NULL;
    }
    $checksum = hash_file(self::ALGORITHM, $path);
    return $checksum === FALSE ? NULL : $checksum;
  }

}
