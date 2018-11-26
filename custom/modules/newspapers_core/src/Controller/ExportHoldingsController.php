<?php

namespace Drupal\newspapers_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class ExportHoldingsController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function exportHoldings() {
    $element = [
      '#markup' => '<dl>
      <dt>Microform</dt>
      <dd><a href="/serials/holdings/export/Microform">https://newspapers.lib.unb.ca/serials/holdings/export/Microform</a></dd>
      <dt>Print</dt>
      <dd><a href="/serials/holdings/export/Print">https://newspapers.lib.unb.ca/serials/holdings/export/Print</a></dd>
      <dt>Digital</dt>
      <dd><a href="/serials/holdings/export/Digital">https://newspapers.lib.unb.ca/serials/holdings/export/Digital</a></dd>
      <dt></a>Online</dt>
      <dd><a href="/serials/holdings/export/Online">https://newspapers.lib.unb.ca/serials/holdings/export/Online</a></dd>
      </dl>',
    ];
    return $element;
  }

}
