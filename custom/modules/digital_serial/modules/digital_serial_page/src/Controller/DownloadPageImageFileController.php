<?php

namespace Drupal\digital_serial\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\fileDownloadController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DownloadSrtFileController object.
 */
class DownloadPageImageFileController extends ControllerBase {

  /**
   * Download a digital serial page.
   *
   * @param int $digital_serial_issue
   *   The issue ID to query.
   * @param int $page_no
   *   The page number to download.
   *
   * @return mixed
   */
  public function servePageEntityFile($digital_serial_issue = NULL, $page_no = NULL) {
    $query = \Drupal::entityQuery('digital_serial_page')
      ->condition('parent_issue', $digital_serial_issue)
      ->condition('page_no', $page_no);

    $entity_ids = $query->execute();
    foreach ($entity_ids as $page_id) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_page');
      $page = $storage->load($page_id);
      $image = $page->getPageImage();
      $uri = $image->getFileUri();
      $request = new Request(array('file' => $uri));
      return fileDownloadController::download($request, 'public');
    }
    throw new NotFoundHttpException();
  }

}
