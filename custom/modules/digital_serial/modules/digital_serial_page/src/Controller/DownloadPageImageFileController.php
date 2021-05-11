<?php

namespace Drupal\digital_serial_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\FileDownloadController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
   *   The digital serial page response.
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
      $absolute_path = \Drupal::service('file_system')->realpath($uri);

      $file_content = file_get_contents($absolute_path);
      $file_name = $image->getFileName();

      $response = new Response($file_content);
      $response->headers->set('Content-Type', $image->getMimeType());
      $response->headers->set('Content-Disposition', "attachment; filename=\"$file_name\"");
      return $response;
    }
    throw new NotFoundHttpException();
  }

}
