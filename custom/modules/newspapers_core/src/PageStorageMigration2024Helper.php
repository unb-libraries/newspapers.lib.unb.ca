<?php

namespace Drupal\newspapers_core;

/**
 * Provides the connection between old and new storage of page files and assets.
 *
 * This was created out of the need to move page assets from the flat single-directory
 * structure to a hierarchical structure that includes directories for each issue.
 * This is necessary to avoid performance issues with large numbers of files in a single
 * directory.
 *
 * @see \Drupal\digital_serial_page\Entity\SerialPage
 */
class PageStorageMigration2024Helper {

// 18229
// scp -r 'retribution:/mnt/storage0/KubeNFS/newspapers-lib-unb-ca/prod/files/serials/pages/18229*' .
// docker cp . 435a2e17a33c:/app/html/sites/default/files/serials/pages/
// chown -R nginx:nginx /app/html/sites/default/files/serials/pages/*
// doas -u $NGINX_RUN_USER -- drush eval "\Drupal\digital_serial_page\PageStorageMigration2024Helper::MoveIssueAssets('18229');"
// doas -u $NGINX_RUN_USER -- drush eval "\Drupal\digital_serial_page\PageStorageMigration2024Helper::bulkCreateNewStoragePaths();"

/**
 * Move all page assets for a given issue to the new storage location.
 */
public static function bulkCreateNewStoragePaths() {
  $issue_ids = \Drupal::entityQuery('digital_serial_issue')
    ->execute();
  $count = count($issue_ids);
  $progress = 0;
  foreach ($issue_ids as $issue_id) {
    $progress += 1;
    echo "Creating issue $progress/$count...";
    $issue_absolute_path = "/app/html/sites/default/files/serials/pages/$issue_id";
    if (!file_exists($issue_absolute_path)) {
      mkdir($issue_absolute_path, 0755, TRUE);
    }
  }
}

/**
 * Move all page assets for a given issue to the new storage location.
 * 
 * @param string $issue_id
 *  The ID of the issue to move.
 */
  public static function moveIssueAssets($issue_id) {
    $issue = \Drupal::entityTypeManager()
    ->getStorage('digital_serial_issue')
    ->load($issue_id);
    self::moveIssuePages($issue);
  }
  
  /**
   * Move all page assets for a given issue to the new storage location.
   * 
   * @param \Drupal\digital_serial_issue\Entity\SerialIssue $issue
   *  The issue to move.
   */
  public static function moveIssuePages($issue) {
    $issue_id = $issue->id();
    $issue->createStoragePath();
  
    $query = \Drupal::entityQuery('digital_serial_page')
      ->condition('parent_issue', $issue_id);
    $entity_ids = $query->execute();
    foreach ($entity_ids as $entity_id) {
      $page = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_page')
        ->load($entity_id);
      $page->movePageImageToPermanentStorage();
      self::moveDziTileLocation($page, $issue);
      self::movePdfFileLocation($page, $issue);
    }
  }

  /**
   * Move the DZI tile location for a page to the new storage location.
   * 
   * @param \Drupal\digital_serial_page\Entity\SerialPage $page
   *  The page to move.
   * @param \Drupal\digital_serial_issue\Entity\SerialIssue $issue
   *  The issue the page belongs to.
   */
  public static function moveDziTileLocation($page, $issue) {
    $file = $page->getPageImage();
    $issue_id = $issue->id();
  
    $file_system = \Drupal::service('file_system');
    $default_file_scheme = \Drupal::config('system.file')->get('default_scheme');
  
    $issue_id = $issue->id();
    $new_issue_path_uri = "$default_file_scheme://serials/pages/$issue_id";
    $new_issue_file_uri = "$new_issue_path_uri/{$file->getFilename()}";
    $old_issue_absolute_file_location = $file_system->realpath($file->getFileUri());
    $new_issue_absolute_file_location = $file_system->realpath($new_issue_file_uri);
  
    $old_dzi_file = str_replace('.jpg', '.dzi', $old_issue_absolute_file_location);
    $old_dzi_asset_path = str_replace('.jpg', '_files', $old_issue_absolute_file_location);
    $new_dzi_file = str_replace('.jpg', '.dzi', $new_issue_absolute_file_location);
    $new_dzi_asset_path = str_replace('.jpg', '_files', $new_issue_absolute_file_location);
  
    if (file_exists($old_dzi_file)) {
      rename($old_dzi_file, $new_dzi_file);
    }
  
    if (file_exists($old_dzi_asset_path)) {
      rename($old_dzi_asset_path, $new_dzi_asset_path);
    }
  }
  
  /**
   * Move the PDF file location for a page to the new storage location.
   * 
   * @param \Drupal\digital_serial_page\Entity\SerialPage $page
   *  The page to move.
   * @param \Drupal\digital_serial_issue\Entity\SerialIssue $issue
   *  The issue the page belongs to.
   */
  public static function movePdfFileLocation($page, $issue) {
    $file = $page->getPageImage();
    $issue_id = $issue->id();
  
    $file_system = \Drupal::service('file_system');
    $default_file_scheme = \Drupal::config('system.file')->get('default_scheme');
  
    $issue_id = $issue->id();
    $new_issue_path_uri = "$default_file_scheme://serials/pages/$issue_id";
    $new_issue_file_uri = "$new_issue_path_uri/{$file->getFilename()}";
    $old_issue_absolute_file_location = $file_system->realpath($file->getFileUri());
    $new_issue_absolute_file_location = $file_system->realpath($new_issue_file_uri);
  
    $old_pdf_file_path = str_replace(
      '/pages/',
      "/pages/pdf/$issue_id/",
      str_replace(
        '.jpg',
        '.pdf',
        $old_issue_absolute_file_location
      )
    );
    $new_pdf_file_path = str_replace(
      '/pages/',
      "/pages/$issue_id/",
      str_replace(
        '.jpg',
        '.pdf',
        $new_issue_absolute_file_location
      )
    );
  
    if (file_exists($old_pdf_file_path)) {
      rename($old_pdf_file_path, $new_pdf_file_path);
    }
  }

}
