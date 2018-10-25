<?php

namespace Drupal\serial_holding_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\serial_holding_export\XlsHoldingExport;

/**
 * HoldingsExport object.
 */
class HoldingsExport extends ControllerBase {

  /**
   * Render the Excel file and serve to user..
   *
   * @param string $holding_type
   *   The type of holding to export.
   *
   * @throws \Exception
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function serveFile($holding_type = NULL) {
    $export = XlsHoldingExport::exportFromType($holding_type);
    return $export->response;
  }

}
