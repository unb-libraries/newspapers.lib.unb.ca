<?php

namespace Drupal\digital_serial_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\digital_serial_page\PageAssetManagement;
use Symfony\Component\HttpFoundation\Response;

/**
 * GetMissingPageAssetsController object.
 */
class GetMissingPageAssetsController extends ControllerBase {

  const PERSISTENT_DRUPAL_FILE_ROOT = '/app/html/sites/default/files';

  /**
   * Retrieves a list of pages missing generated PDF files.
   *
   * @param int $limit
   *   The number of pages to return. Defaults to 50.
   * @param int $skip
   *   The number of pages to skip evaluating. Defaults to 0.
   *
   * @return mixed
   *   The response.
   */
  public function serveMissingFilesWithMissingPdfs($limit = 50, $skip = 0) {
    $missing_pages = PageAssetManagement::getMissingPdfPages(self::PERSISTENT_DRUPAL_FILE_ROOT, $limit, $skip);
    $response = new Response();

    if (empty($missing_pages)) {
      $response->setContent(json_encode([]));
      $response->headers->set('Content-Type', 'application/json');
      return $response;
    }

    $response->setContent(json_encode($missing_pages));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

  /**
   * Retrieves a list of pages missing generated DZI assets.
   *
   * @param int $limit
   *   The number of pages to return. Defaults to 50.
   * @param int $skip
   *   The number of pages to skip evaluating. Defaults to 0.
   *
   * @return mixed
   *   The response.
   */
  public function serveMissingFilesWithMissingDzis($limit = 50, $skip = 0) {
    $missing_pages = PageAssetManagement::getMissingDziPages(self::PERSISTENT_DRUPAL_FILE_ROOT, $limit, $skip);
    $response = new Response();

    if (empty($missing_pages)) {
      $response->setContent(json_encode([]));
      $response->headers->set('Content-Type', 'application/json');
      return $response;
    }

    $response->setContent(json_encode($missing_pages));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

}
