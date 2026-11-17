<?php

namespace Drupal\digital_serial_page;

/**
 * Provides page asset management helpers.
 *
 * @see \Drupal\digital_serial_issue\Entity\SerialIssue
 * @see \Drupal\digital_serial_page\Entity\SerialPage
 */
class PageAssetManagement {

  /**
   * The largest number of pages one request may return.
   */
  const MAX_LIMIT = 500;

  /**
   * The number of page images read from the database per batch.
   */
  const BATCH_SIZE = 500;

  /**
   * Seconds a request may spend checking files before it returns.
   *
   * Leaves headroom under the 60s proxy timeout.
   */
  const TIME_BUDGET = 40;

  /**
   * Prefix of the State keys holding each asset's checked-up-to file ID.
   */
  const WATERMARK_STATE_PREFIX = 'digital_serial_page.missing_asset_checked_fid.';

  /**
   * The asset types whose absence can be listed.
   */
  const ASSET_TYPES = ['dzi', 'pdf'];

  /**
   * Gets pages with missing dzi files.
   *
   * @param string $file_root
   *   The path to the root of the persistent Drupal filesystem.
   * @param int $limit
   *   The number of records to return, at most MAX_LIMIT.
   * @param int $skip
   *   The number of records to skip evaluating.
   *
   * @return array
   *   See ::getPagesWithMissingAsset().
   */
  public static function getMissingDziPages(string $file_root, int $limit = 50, int $skip = 0): array {
    return self::getPagesWithMissingAsset('dzi', $limit, $skip, function (array $file_info) use ($file_root) {
      return !file_exists($file_root . '/' . $file_info['rel_dzi_filepath']) ||
        !is_dir($file_root . '/' . $file_info['rel_dzi_dirpath']);
    }, $file_root);
  }

  /**
   * Gets pages with missing pdf files.
   *
   * @param string $file_root
   *   The path to the root of the persistent Drupal filesystem.
   * @param int $limit
   *   The number of records to return, at most MAX_LIMIT.
   * @param int $skip
   *   The number of records to skip evaluating.
   *
   * @return array
   *   See ::getPagesWithMissingAsset().
   */
  public static function getMissingPdfPages(string $file_root, int $limit = 50, int $skip = 0): array {
    return self::getPagesWithMissingAsset('pdf', $limit, $skip, function (array $file_info) use ($file_root) {
      return !file_exists($file_root . '/' . $file_info['rel_pdf_filepath']);
    }, $file_root);
  }

  /**
   * Forgets how far the missing-asset scan has checked.
   *
   * The next request with skip 0 then rechecks every page image. Needed when
   * assets are deleted from pages the scan has already passed.
   *
   * @param string $asset
   *   One of ASSET_TYPES.
   */
  public static function resetScan(string $asset): void {
    \Drupal::state()->delete(self::WATERMARK_STATE_PREFIX . $asset);
  }

  /**
   * Gets the file ID up to which every page image has the asset.
   *
   * @param string $asset
   *   One of ASSET_TYPES.
   *
   * @return int
   *   The file ID, or 0 when nothing is recorded.
   */
  public static function getScanWatermark(string $asset): int {
    return (int) \Drupal::state()->get(self::WATERMARK_STATE_PREFIX . $asset, 0);
  }

  /**
   * Walks the page images, collecting those missing an asset.
   *
   * Stops at $limit matches, at the last image, or after TIME_BUDGET seconds.
   * With $skip 0 the walk starts after a stored watermark: the highest file ID
   * below which every page image was found to have the asset (or cannot be
   * given one). The walk moves the watermark up to the first page found
   * missing the asset, so later requests skip the pages already checked.
   * With $skip above 0 the walk starts at that offset and ignores the
   * watermark. Only the first batch uses OFFSET; later batches continue from
   * the last file ID read.
   *
   * @param string $asset
   *   One of ASSET_TYPES.
   * @param int $limit
   *   The number of records to return, at most MAX_LIMIT.
   * @param int $skip
   *   The number of records to skip evaluating.
   * @param callable $is_missing
   *   Returns TRUE when the given file info lacks the asset.
   * @param string $file_root
   *   The path to the root of the persistent Drupal filesystem.
   *
   * @return array
   *   An array with keys:
   *   - pages: file infos keyed by file ID.
   *   - complete: FALSE when the time budget ran out before the walk reached
   *     $limit matches or the last image.
   */
  protected static function getPagesWithMissingAsset(string $asset, int $limit, int $skip, callable $is_missing, string $file_root): array {
    $limit = min(max($limit, 0), self::MAX_LIMIT);
    $skip = max($skip, 0);
    $use_watermark = $skip === 0;
    $old_watermark = $use_watermark ? self::getScanWatermark($asset) : 0;
    $watermark = $old_watermark;
    $deadline = microtime(TRUE) + self::TIME_BUDGET;
    $pages_with_missing = [];
    $after_fid = $use_watermark ? $watermark : NULL;
    $complete = TRUE;

    while (count($pages_with_missing) < $limit) {
      $file_infos = $after_fid === NULL
        ? self::getPageImagesInfo(self::BATCH_SIZE, $skip)
        : self::getPageImagesInfo(self::BATCH_SIZE, 0, $after_fid);
      if (empty($file_infos)) {
        break;
      }
      foreach ($file_infos as $fid => $file_info) {
        if (microtime(TRUE) > $deadline) {
          $complete = FALSE;
          break 2;
        }
        $after_fid = $fid;
        if (self::pageLacksAsset($file_info, $file_root, $is_missing)) {
          $pages_with_missing[$fid] = $file_info;
          if (count($pages_with_missing) >= $limit) {
            break 2;
          }
        }
        elseif (empty($pages_with_missing)) {
          $watermark = $fid;
        }
      }
    }

    if ($use_watermark && $watermark > $old_watermark) {
      \Drupal::state()->set(self::WATERMARK_STATE_PREFIX . $asset, $watermark);
    }

    return [
      'pages' => $pages_with_missing,
      'complete' => $complete,
    ];
  }

  /**
   * Determines whether a page image lacks an asset that can be generated.
   *
   * Checks the asset before the image, so a page that has its asset costs no
   * stat of the image.
   *
   * @param array $file_info
   *   The file info.
   * @param string $file_root
   *   The path to the root of the persistent Drupal filesystem.
   * @param callable $is_missing
   *   Returns TRUE when the given file info lacks the asset.
   *
   * @return bool
   *   TRUE when the page belongs to an issue, its image exists, and it lacks
   *   the asset.
   */
  protected static function pageLacksAsset(array $file_info, string $file_root, callable $is_missing): bool {
    return !empty($file_info['title_id']) &&
      !empty($file_info['issue_id']) &&
      $is_missing($file_info) &&
      file_exists($file_root . '/' . $file_info['rel_image_path']);
  }

  /**
   * Delivers metadata for page images in database.
   *
   * @param int $limit
   *    The number of records to return.
   * @param int $offset
   *    The number of records to skip.
   * @param int|null $after_fid
   *    When set, only return files with a higher file ID.
   *
   * @return array
   *    An array of image info.
   */
  static function getPageImagesInfo(
    int $limit = 50,
    int $offset = 0,
    ?int $after_fid = NULL
    ) : array {
    $after_condition = $after_fid === NULL ? '' : 'AND fm.fid > :after_fid';
    $sql = <<<EOT
    SELECT fm.fid, fm.uri, dst.id AS title_id, dsi.id AS issue_id
    FROM file_managed fm
    LEFT JOIN digital_serial_page dsp
    ON fm.fid = dsp.page_image__target_id
    LEFT JOIN digital_serial_issue dsi
    ON dsp.parent_issue = dsi.id
    LEFT JOIN digital_serial_title dst
    ON dsi.parent_title = dst.id
    WHERE fm.uri LIKE 'public://serials/pages/%.jpg'
    $after_condition
    ORDER BY fm.fid ASC
    LIMIT $limit
    OFFSET $offset;
EOT;
    $args = $after_fid === NULL ? [] : [':after_fid' => $after_fid];
    $result = \Drupal::database()->query($sql, $args);
    $images = [];
    while ($row = $result->fetchAssoc()) {
      $image_file_data = pathinfo($row['uri']);
      $issue_id = $row['issue_id'];
      $title_id = $row['title_id'];
      $extensionless_filename = $image_file_data['filename'];
      # Ex: serials/pages/100/18086/18086-0001.jpg
      $rel_image_path = str_replace('public://', '', $row['uri']);
      $rel_dzi_filepath = "serials/dzi/$title_id/$issue_id/$extensionless_filename.dzi";
      $rel_dzi_dirpath = "serials/dzi/$title_id/$issue_id/{$extensionless_filename}_files";
      $rel_pdf_filepath = "serials/pdf/$title_id/$issue_id/$extensionless_filename.pdf";
      $images[$row['fid']] = [
        'fid' => $row['fid'],
        'uri' => $row['uri'],
        'title_id' => $title_id,
        'issue_id' => $issue_id,
        'rel_image_path' => $rel_image_path,
        'rel_dzi_filepath' => $rel_dzi_filepath,
        'rel_dzi_dirpath' => $rel_dzi_dirpath,
        'rel_pdf_filepath' => $rel_pdf_filepath,
      ];
    }
    return $images;
  }

}
