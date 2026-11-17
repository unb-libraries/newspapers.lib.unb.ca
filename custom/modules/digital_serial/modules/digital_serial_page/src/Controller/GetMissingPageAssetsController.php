<?php

namespace Drupal\digital_serial_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\digital_serial_page\PageAssetManagement;
use Symfony\Component\HttpFoundation\JsonResponse;

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
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function serveMissingFilesWithMissingPdfs($limit = 50, $skip = 0) {
    return $this->buildResponse(PageAssetManagement::getMissingPdfPages(self::PERSISTENT_DRUPAL_FILE_ROOT, $limit, $skip));
  }

  /**
   * Retrieves a list of pages missing generated DZI assets.
   *
   * @param int $limit
   *   The number of pages to return. Defaults to 50.
   * @param int $skip
   *   The number of pages to skip evaluating. Defaults to 0.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function serveMissingFilesWithMissingDzis($limit = 50, $skip = 0) {
    return $this->buildResponse(PageAssetManagement::getMissingDziPages(self::PERSISTENT_DRUPAL_FILE_ROOT, $limit, $skip));
  }

  /**
   * Builds the response for a missing-asset scan.
   *
   * A scan that ran out of time without finding a page answers 503, so
   * clients do not read an empty list as "nothing is missing". The scan has
   * saved its progress, so retrying continues where it stopped.
   *
   * @param array $result
   *   The result of PageAssetManagement::getPagesWithMissingAsset().
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  protected function buildResponse(array $result) {
    if (empty($result['pages']) && !$result['complete']) {
      return new JsonResponse(
        ['error' => 'The scan for missing assets ran out of time before finding a page. It saved its progress; retry the request.'],
        503,
        ['Retry-After' => '0'],
      );
    }
    return new JsonResponse($result['pages']);
  }

}
