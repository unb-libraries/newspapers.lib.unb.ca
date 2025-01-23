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
   * Gets pages with missing dzi files.
   *
   * @param string $file_root
   *   The path to the root of the persistent Drupal filesystem.
   * @param int $limit
   *   The number of records to return.
   *
   * @return array
   *   An array of pages with missing dzi files.
   */
  public static function getMissingDziPages(string $file_root, int $limit = 50) {
    $pages_with_missing = [];
    $while_counter = 0;
    while (count($pages_with_missing) < $limit) {
      $file_infos = self::getPageImagesInfo($limit, $while_counter * $limit);
      $while_counter++;
      if (empty($file_infos)) {
        continue;
      }
      foreach ($file_infos as $fid => $file_info) {
        if (!self::fileShouldBeChecked($file_info, $file_root)) {
          continue;
        }
        $dzi_file_path = $file_root . '/' . $file_info['rel_dzi_filepath'];
        if (!file_exists($dzi_file_path)) {
          $pages_with_missing[$fid] = $file_info;
          continue;
        }
        $dzi_dir_path = $file_root . '/' . $file_info['rel_dzi_dirpath'];
        if (!is_dir($dzi_dir_path)) {
          $pages_with_missing[$fid] = $file_info;
          continue;
        }
      }
    }
    return $pages_with_missing;
  }

  /**
   * Gets pages with missing pdf files.
   *
   * @param string $file_root
   *   The path to the root of the persistent Drupal filesystem.
   * @param int $limit
   *   The number of records to return.
   *
   * @return array
   *   An array of pages with missing pdf files.
   */
  public static function getMissingPdfPages(string $file_root, int $limit = 50) {
    $pages_with_missing = [];
    $while_counter = 0;
    while (count($pages_with_missing) < $limit) {
      $file_infos = self::getPageImagesInfo($limit, $while_counter * $limit);
      $while_counter++;
      if (empty($file_infos)) {
        continue;
      }
      foreach ($file_infos as $fid => $file_info) {
        if (!self::fileShouldBeChecked($file_info, $file_root)) {
          continue;
        }
        $pdf_file_path = $file_root . '/' . $file_info['rel_pdf_filepath'];
        if (!file_exists($pdf_file_path)) {
          $pages_with_missing[$fid] = $file_info;
          continue;
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
   *
   * @return array
   *    An array of image info.
   */
  static function getPageImagesInfo(
    int $limit = 50,
    int $offset = 0
    ) : array {
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
    LIMIT $limit
    OFFSET $offset;
EOT;
    $result = \Drupal::database()->query($sql);
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
