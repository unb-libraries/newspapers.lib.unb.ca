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
   *   An array of pages with missing dzi files.
   */
  public static function getMissingDziPages(string $file_root, int $limit = 50, int $skip = 0) {
    return self::getPagesWithMissingAsset($limit, $skip, function (array $file_info) use ($file_root) {
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
   *   An array of pages with missing pdf files.
   */
  public static function getMissingPdfPages(string $file_root, int $limit = 50, int $skip = 0) {
    return self::getPagesWithMissingAsset($limit, $skip, function (array $file_info) use ($file_root) {
      return !file_exists($file_root . '/' . $file_info['rel_pdf_filepath']);
    }, $file_root);
  }

  /**
   * Walks the page images once, collecting those missing an asset.
   *
   * Stops at $limit matches or at the last image. Only the first batch uses
   * OFFSET; later batches continue from the last file ID read, so a full walk
   * reads each row once.
   *
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
   *   File infos keyed by file ID.
   */
  protected static function getPagesWithMissingAsset(int $limit, int $skip, callable $is_missing, string $file_root): array {
    $limit = min(max($limit, 0), self::MAX_LIMIT);
    $skip = max($skip, 0);
    $pages_with_missing = [];
    $after_fid = NULL;
    while (count($pages_with_missing) < $limit) {
      $file_infos = $after_fid === NULL
        ? self::getPageImagesInfo(self::BATCH_SIZE, $skip)
        : self::getPageImagesInfo(self::BATCH_SIZE, 0, $after_fid);
      if (empty($file_infos)) {
        break;
      }
      foreach ($file_infos as $fid => $file_info) {
        $after_fid = $fid;
        if (!self::fileShouldBeChecked($file_info, $file_root) || !$is_missing($file_info)) {
          continue;
        }
        $pages_with_missing[$fid] = $file_info;
        if (count($pages_with_missing) >= $limit) {
          break;
        }
      }
    }
    return $pages_with_missing;
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

  /**
   * Determines if a file should be checked for missing assets.
   *
   * @param array $file_info
   *   The file info.
   * @param string $image_root
   *   The image root.
   * @return bool
   *   Whether the file should be checked.
   */
  static function fileShouldBeChecked(array $file_info, string $file_root) {
    $file_path = $file_root . '/' . $file_info['rel_image_path'];
    return !empty($file_info['title_id']) &&
      !empty($file_info['issue_id']) &&
      file_exists($file_path);
  }

}
