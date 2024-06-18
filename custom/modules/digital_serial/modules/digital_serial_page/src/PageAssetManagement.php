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
   * Gets all page images.
   *
   * @param array $columns
   *   The columns to return.
   *
   * @return array
   *   An array of page images fids.
   */
  public static function getPageImages(array $columns = ['uri']) {
    if (!in_array('fid', $columns)) {
      $columns[] = 'fid';
    }
    $column_string = implode(',', $columns);
    $sql = "SELECT $column_string from file_managed WHERE uri LIKE 'public://serials/pages/%.jpg'";
    $result = \Drupal::database()->query($sql);
    $images = [];
    while ($row = $result->fetchAssoc()) {
      $images[$row['fid']] = $row;
    }
    return $images;
  }

  /**
   * Gets all page image absolute paths.
   *
   * @return array
   *   An array of page images with absolute paths.
   */
  public static function getPageImagesAbsPath() {
    $page_images = self::getPageImages(['uri']);
    $abs_page_paths = [];
    foreach ($page_images as $page_id => $image_data) {
      $file_path = str_replace('public://', DRUPAL_ROOT. '/sites/default/files/', $image_data['uri']);
      $abs_page_paths[$page_id] = $file_path;
    }
    return $abs_page_paths;
  }

  /**
   * Gets page images that are missing a an asset file.
   *
   * @param string $type
   *   The type of image asset file to check for (dzi, pdf).
   * @param int $limit
   *   The maximum number of files to return. Defaults to no limit.
   *
   * @return array
   *   An array of page images that are missing the specified asset file.
   */
  public static function getMissingImageAssetFiles($type, $limit = -1) {
    $page_images = self::getPageImagesAbsPath();
    $missing_dzi_files = [];
    foreach ($page_images as $page_id => $file_path) {
      $dzi_file_path = str_replace('.jpg', ".$type", $file_path);
      if (!file_exists($dzi_file_path)) {
        $missing_dzi_files[$page_id] = $file_path;
      }
      if ($limit > 0 && count($missing_dzi_files) >= $limit) {
        break;
      }
    }
    return $missing_dzi_files;
  }

  public static function getMissingDziFilesCount() {
    print_r(self::getMissingImageAssetFiles('dzi', 50));
  }

}
