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

  const BASE_PAGE_STORAGE_PATH = '/serials/pages';

// 18237
// rm -rf /app/html/sites/default/files/serials/pages/*
// doas -u $NGINX_RUN_USER -- drush eval "\Drupal\newspapers_core\PageStorageMigration2024Helper::bulkCreateNewStoragePaths();"
// scp -r 'retribution:/mnt/storage0/KubeNFS/newspapers-lib-unb-ca/prod/files/serials/pages/18237*' .
// docker cp . 435a2e17a33c:/app/html/sites/default/files/serials/pages/
// mkdir -p /app/html/sites/default/files/serials/pages/pdf/18237
// cp /app/html/sites/default/files/serials/pages/18237-0001.jpg /app/html/sites/default/files/serials/pages/pdf/18237/18237-0001.pdf
// cp /app/html/sites/default/files/serials/pages/18237-0002.jpg /app/html/sites/default/files/serials/pages/pdf/18237/18237-0002.pdf
// chown -R nginx:nginx /app/html/sites/default/files/serials/pages/*
// doas -u $NGINX_RUN_USER -- drush eval "\Drupal\newspapers_core\PageStorageMigration2024Helper::MoveIssueAssets('18237');"

/**
 * Move all page assets for a given issue to the new storage location.
 */
public static function bulkCreateNewStoragePaths() {
  $issue_ids = \Drupal::entityQuery('digital_serial_issue')
    ->execute();
  $count = count($issue_ids);
  $progress = 0;
  foreach ($issue_ids as $issue_id) {
    // First, unlink the old storage location.
    $old_storage_location = DRUPAL_ROOT . self::BASE_PAGE_STORAGE_PATH . "/$issue_id";
    if (file_exists($old_storage_location)) {
      unlink($old_storage_location);
    }

    $issue = \Drupal::entityTypeManager()
      ->getStorage('digital_serial_issue')
      ->load($issue_id);
    $title_id = $issue->getParentTitleId();
    $progress += 1;
    echo "Creating issue $progress/$count...\n";
    $issue_absolute_path = DRUPAL_ROOT . self::BASE_PAGE_STORAGE_PATH . "/$title_id/$issue_id";
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
      self::movePageAssetsById($entity_id);
    }
  }

  /**
   * Move all page assets for a page.
   *
   * @param int $page_id
   *   The ID of the page to move.
   */
  public static function movePageAssetsById($page_id) {
    $page = \Drupal::entityTypeManager()
      ->getStorage('digital_serial_page')
      ->load($page_id);
    if ($page == NULL) {
      return;
    }
    self::movePageAssets($page);
  }

  /**
   * Move all page assets for a page.
   *
   * @param \Drupal\digital_serial_page\Entity\SerialPage $page
   *   The page to move.
   * @param \Drupal\digital_serial_issue\Entity\SerialIssue $issue
   *   The issue to move. If not provided, the issue will be loaded from the page.
   */
  public static function movePageAssets($page, $issue = NULL) {
    if ($issue == NULL) {
      $issue = $page->getParentIssue();
    }
    if ($issue == NULL) {
      return;
    }
    $page->movePageImageToPermanentStorage();
    self::moveDziTileLocation($page, $issue);
    self::movePdfFileLocation($page, $issue);
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
    $title_id = $issue->getParentTitleId();
    $image_file_name = $file->getFilename();
    $file_name = str_replace('.jpg', '.dzi', $image_file_name);

    $old_page_absolute_file_location = DRUPAL_ROOT . self::BASE_PAGE_STORAGE_PATH . "/$file_name";
    $new_page_absolute_file_location = DRUPAL_ROOT . self::BASE_PAGE_STORAGE_PATH . "/$title_id/$issue_id/$file_name";
    $old_dzi_file = str_replace('.jpg', '.dzi', $old_page_absolute_file_location);
    $old_dzi_asset_path = str_replace('.dzi', '_files', $old_dzi_file);
    $new_dzi_file = str_replace('.jpg', '.dzi', $new_page_absolute_file_location);
    $new_dzi_asset_path = str_replace('.dzi', '_files', $new_dzi_file);

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
    $title_id = $issue->getParentTitleId();
    $file_name = $file->getFilename();
    $old_page_absolute_file_location = DRUPAL_ROOT . self::BASE_PAGE_STORAGE_PATH . "/$file_name";
    $new_page_absolute_file_location = DRUPAL_ROOT . self::BASE_PAGE_STORAGE_PATH . "/$title_id/$issue_id/$file_name";
  
    $old_pdf_file_path = str_replace(
      '/pages/',
      "/pages/pdf/$issue_id/",
      str_replace(
        '.jpg',
        '.pdf',
        $old_page_absolute_file_location
      )
    );
    $new_pdf_file_path = str_replace(
      '.jpg',
      '.pdf',
      $new_page_absolute_file_location
    );
  
    if (file_exists($old_pdf_file_path)) {
      rename($old_pdf_file_path, $new_pdf_file_path);
    }
  }

  /**
   * Get a list of pages with files stored in the old location.
   *
   * @param int $limit
   *   The number of pages to return. Defaults to 50.
   */
  public static function getPagesNeedingToMove($limit = 50) {
    $pages = [];
    $sql = "SELECT fid from file_managed WHERE uri NOT LIKE 'public://serials/pages/%/%/%.jpg' AND uri LIKE 'public://serials/pages/%' LIMIT $limit";
    $result = \Drupal::database()->query($sql);
    $fids = $result->fetchCol();
    foreach ($fids as $fid) {
      $file = \Drupal\file\Entity\File::load($fid);
      $page = \Drupal::entityTypeManager()
        ->getStorage('digital_serial_page')
        ->loadByProperties(['page_image' => $file->id()]);
      if ($page != NULL) {
        $pages[] = $page;
      }
    }
    return $pages;
  }

  /**
   * Move all pages with files stored in the old location to the new location.
   *
   * @param int $limit
   *   The number of pages to move. Defaults to 50.
   */
  public static function movePagesNeedingToMove($limit = 50) {
    $pages = self::getPagesNeedingToMove($limit);
    foreach ($pages as $page) {
      self::movePageAssets($page);
    }
  }

}
