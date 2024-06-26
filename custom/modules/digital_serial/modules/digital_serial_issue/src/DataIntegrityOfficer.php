<?php

namespace Drupal\digital_serial_issue;

/**
 * Provides data integrity helpers.
 *
 * doas -u $NGINX_RUN_USER -- drush eval "\Drupal\digital_serial_issue\DataIntegrityOfficer::MoveIssueAssets('18237');"
 *
 * @see \Drupal\digital_serial_issue\Entity\SerialIssue
 * @see \Drupal\digital_serial_page\Entity\SerialPage
 */
class DataIntegrityOfficer
{

  const EMPTY_DATA_VALUE = '--';

  /**
   * Gets all page images that are not associated with any page.
   *
   * @return \Drupal\file\Entity\File[]
   *   An array of file entities that are orphaned.
   */
  public static function getOrphanedPageImages()
  {
    $sql = "SELECT fid from file_managed WHERE uri LIKE 'public://serials/pages/%.jpg' AND fid NOT IN (SELECT fid FROM file_usage)";
    $result = \Drupal::database()->query($sql);
    $fids = $result->fetchCol();
    $files = [];
    foreach ($fids as $fid) {
      $file = \Drupal\file\Entity\File::load($fid);
      $files[] = $file;
    }
    return $files;
  }

  /**
   * Deletes all page images that are not associated with any page.
   */
  public static function deleteOrphanedPageImageFiles()
  {
    $files = self::getOrphanedPageImages();
    foreach ($files as $file) {
      $file->delete();
    }
  }

  /**
   * Gets the filenames of all page images that are not associated with any page.
   *
   * @return string[]
   *   An array of filenames that are orphaned.
   */
  public static function reportOrphanedPageImages()
  {
    $files = self::getOrphanedPageImages();
    $items = [];
    foreach ($files as $file) {
      $items[] = [
        'fid' => $file->id(),
        'filename' => $file->getFilename(),
        'uri' => $file->getFileUri(),
      ];
    }
    return $items;
  }

  /**
   * Gets all issues that do not have any pages.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssue[]
   *   An array of issue entities that do not have any pages.
   */
  public static function getIssuesWithNoPages()
  {
    $sql = "SELECT id FROM digital_serial_issue WHERE id NOT IN (SELECT parent_issue FROM digital_serial_page)";
    $result = \Drupal::database()->query($sql);
    $ids = $result->fetchCol();
    $issues = [];
    foreach ($ids as $id) {
      $issue_entity = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_issue')
        ->load($id);
      $issues[] = $issue_entity;
    }
    return $issues;
  }

  /**
   * Deletes all issues that do not have any pages.
   */
  public static function deleteIssuesWithNoPages()
  {
    $issues = self::getIssuesWithNoPages();
    foreach ($issues as $issue) {
      $issue->delete();
    }
  }

  /**
   * Builds a array of issues that have no pages.
   *
   * @return array[]
   *   An associative array of issue info without any associated image files.
   */
  public static function reportIssuesWithNoPages()
  {
    $issues = self::getIssuesWithNoPages();
    $items = [];
    foreach ($issues as $issue) {
      $parent_title = $issue->getParentTitle();
      $items[] = [
        'issue_id' => $issue->id(),
        'parent_title_id' => $parent_title != NULL ? $parent_title->id() : self::EMPTY_DATA_VALUE,
        'parent_title' => $parent_title != NULL && $parent_title->getParentPublication() != NULL ? $parent_title->getParentPublication()->label() : self::EMPTY_DATA_VALUE,
        'issue_date' => !empty($issue->get('issue_date')->date) ? $issue->get('issue_date')->date->format('Y-m-d') : self::EMPTY_DATA_VALUE,
      ];
    }
    return $items;
  }

  /**
   * Builds a report from an array of items.
   *
   * @param array $items
   *   An array of items to build a report from.
   * @param string $separator
   *   The separator to use between items.
   *
   * @return string
   *   An array of page entities that do not have a file associated with them.
   */
  public static function getFormattedReport(
    array $items,
    $separator = "\t"
  ) {
    $output = '';
    $titles_printed = FALSE;
    foreach ($items as $item) {
      if (!$titles_printed) {
        $titles = array_keys($item);
        $output .= implode($separator, $titles);
        $output .= "\n";
        $titles_printed = TRUE;
      }
      $filled_item = self::fillEmptyValuesWithString($item);
      $output .= implode($separator, $filled_item);
      $output .= "\n";
    }
    return $output;
  }

  /**
   * Identifies any data integrity issues that may exist within the system.
   *
   * @return array
   *   An associative array of issues and its associated data.
   */
  public static function getDataIntegrityIssues($print_info = FALSE)
  {
    $report = [];
    $report['Orphaned Issues'] = self::getFormattedReport(
      self::reportOrphanedIssues()
    );
    $report['Orphaned Page Images'] = self::getFormattedReport(
      self::reportOrphanedPageImages()
    );
    $report['Issues Without Pages'] = self::getFormattedReport(
      self::reportIssuesWithNoPages()
    );
    $report['Pages With Invalid Images'] = self::getFormattedReport(
      self::reportPagesWithMissingFiles($print_info)
    );
    return $report;
  }

  /**
   * Prints a report of any data integrity issues that may exist within the system.
   */
  public static function printDataIntegrityIssues($print_info = FALSE)
  {
    $report = self::getDataIntegrityIssues($print_info);

    foreach ($report as $label => $data) {
      if (empty($data)) {
        continue;
      }

      $report .= "$label\n";
      $report .= "--------------------------------\n";
      $report .= $data;
      $report .= "\n";
    }
    print_r($report);
  }

  /**
   * Fills empty values in an array with a specified string.
   */
  public static function fillEmptyValuesWithString(
    array $values,
    $string = self::EMPTY_DATA_VALUE
  ) {
    foreach ($values as $key => $value) {
      if (empty($value)) {
        $values[$key] = $string;
      }
    }
    return $values;
  }

  /**
   * Identifies any issues that have an invalid parent title.
   *
   * @return array
   *   An array of issues that have an invalid parent title.
   */
  public static function reportOrphanedIssues()
  {
    $issues = self::getNullTitleIssues();
    $items = [];
    foreach ($issues as $issue) {
      $items[] = [
        'issue_id' => $issue->id(),
        'parent_id' => NULL,
      ];
    }
    $issues = self::getOrphanedIssues();
    foreach ($issues as $issue) {
      $items[] = [
        'issue_id' => $issue->id(),
        'parent_id' => $issue->getParentTitleId(),
      ];
    }
    return $items;
  }

  /**
   * Gets all issues that have a NULL parent title.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssue[]
   *   An array of issue entities that have a NULL parent title.
   */
  public static function getNullTitleIssues()
  {
    $sql = "SELECT id FROM digital_serial_issue WHERE parent_title IS NULL";
    $result = \Drupal::database()->query($sql);
    $ids = $result->fetchCol();
    $issues = [];
    foreach ($ids as $id) {
      $issue_entity = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_issue')
        ->load($id);
      $issues[] = $issue_entity;
    }
    return $issues;
  }

  /**
   * Gets all issues that have an invalid parent title.
   *
   * @return \Drupal\digital_serial_issue\Entity\SerialIssue[]
   *   An array of issue entities that have an invalid parent title.
   */
  public static function getOrphanedIssues()
  {
    $sql = "SELECT * FROM digital_serial_issue WHERE parent_title NOT IN (SELECT id FROM digital_serial_title)";
    $result = \Drupal::database()->query($sql);
    $ids = $result->fetchCol();
    $issues = [];
    foreach ($ids as $id) {
      $issue_entity = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_issue')
        ->load($id);
      $issues[] = $issue_entity;
    }
    return $issues;
  }

  /**
   * Identifies any pages that do not have a valid image file associated with them.
   *
   * @return array
   *   An array of page entities that do not have a valid image file associated with them.
   */
  public static function reportPagesWithMissingFiles($print_info = FALSE)
  {
    // Case 1: No FID referenced.
    $pages = self::getMissingFilesNoFid();
    $items = [];
    foreach ($pages as $page) {
      $items[] = [
        'page_id' => $page->id(),
        'fid' => NULL,
        'uri' => NULL,
        'path' => NULL,
        'details' => 'No Image Referenced',
      ];
    }

    // Case 2&3: FID referenced but no file, or file zero length.
    // This is a large operation, so we process in chunks.
    $item_count = 250;
    $offset = 0;
    $returned_items = $item_count;

    while ($returned_items == $item_count) {
      $memuse = memory_get_usage();
      if ($print_info) {
        echo "Processing $item_count items starting at $offset [Memory: $memuse]\n";
      }
      $pages = self::getPageFilesDetail($offset, $item_count);
      $returned_items = count($pages);
      $offset += $item_count;

      foreach ($pages as $page) {
        if (!file_exists($page['path'])) {
          $items[] = [
            'page_id' => $page['id'],
            'fid' => $page['fid'],
            'uri' => $page['uri'],
            'path' => $page['path'],
            'details' => 'File DNE',
            'url' => $page['url'],
          ];
        } elseif (filesize($page['path']) == 0) {
          $items[] = [
            'page_id' => $page['id'],
            'fid' => $page['fid'],
            'uri' => $page['uri'],
            'path' => $page['path'],
            'details' => 'Zero length',
            'url' => $page['url'],
          ];
        }
      }
      // Clear memory cache to prevent memory exhaustion.
      \Drupal::service('entity.memory_cache')->deleteAll();
    }

    return $items;
  }

  /**
   * Gets all pages that do not have an associated file.
   *
   * @return \Drupal\digital_serial_page\Entity\SerialPage[]
   *   An array of page entities that do not have an associated file.
   */
  public static function getMissingFilesNoFid()
  {
    $sql = "SELECT id FROM digital_serial_page WHERE page_image__target_id IS NULL";
    $result = \Drupal::database()->query($sql);
    $ids = $result->fetchCol();
    $pages = [];
    foreach ($ids as $id) {
      $page_entity = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_page')
        ->load($id);
      $pages[] = $page_entity;
    }
    return $pages;
  }

  /**
   * Gets details of image files associated with serial pages.
   *
   * @return array[]
   *   An array of assoiative arrays of page details.
   */
  public static function getPageFilesDetail($offset = 0, $limit = 50)
  {
    $sql = "SELECT id FROM digital_serial_page WHERE page_image__target_id IS NOT NULL ORDER BY id LIMIT $limit OFFSET $offset";
    $result = \Drupal::database()->query($sql);
    $ids = $result->fetchCol();
    $pages = [];
    unset($result);

    foreach ($ids as $id) {
      $page_entity = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_page')
        ->load($id);
      $file = $page_entity->getPageImage();
      $issue = $page_entity->getParentIssue();
      $title = $issue->getParentTitle();
      $url = sprintf(
        'https://newspapers.lib.unb.ca/serials/%s/issues/%s/pages/%s',
        $title->id(),
        $issue->id(),
        $page_entity->id()
      );
      $abs_file_path = DRUPAL_ROOT . str_replace('public://', '/sites/default/files/', $file->getFileUri());
      unset($file);
      $pages[] = [
        'id' => $page_entity->id(),
        'fid' => $page_entity->getPageImage()->target_id,
        'uri' => $page_entity->getPageImage()->getFileUri(),
        'path' => $abs_file_path,
        'url' => $url,
      ];
      unset($page_entity);
    }
    return $pages;
  }

}
