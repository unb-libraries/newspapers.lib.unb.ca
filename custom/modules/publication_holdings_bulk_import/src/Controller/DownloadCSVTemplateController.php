<?php

namespace Drupal\publication_holdings_bulk_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DownloadCSVTemplateController object.
 */
class DownloadCSVTemplateController extends ControllerBase {

  /**
   * Render a template of a CSV format.
   *
   * @param string $format_id
   *   The format ID to render the template for.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The symfony response object.
   */
  public function serveFile($format_id = NULL) {
    $import_format = _publication_holdings_bulk_import_get_import_format($format_id);

    if (empty($import_format['columns'])) {
      throw new NotFoundHttpException();
    }

    $csv = Writer::createFromFileObject(new \SplTempFileObject());
    $columns = [];
    foreach ($import_format['columns'] as $column) {
      $columns[] = $column['name'];
    }

    $csv->insertOne($columns);

    $response = new Response($csv->__toString());
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', "attachment; filename=\"{$format_id}_template.csv\"");
    return $response;
  }

}
