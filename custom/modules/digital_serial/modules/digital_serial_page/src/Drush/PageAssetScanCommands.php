<?php

namespace Drupal\digital_serial_page\Drush;

use Drupal\digital_serial_page\PageAssetManagement;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the missing page asset scan.
 */
class PageAssetScanCommands extends DrushCommands {

  /**
   * Makes the missing-asset scan recheck every page image.
   *
   * Use after deleting DZI or PDF files of pages the scan has already passed.
   *
   * @param string $asset
   *   The asset to reset: dzi, pdf or all.
   *
   * @command digital-serial-page:reset-missing-asset-scan
   *
   * @usage drush digital-serial-page:reset-missing-asset-scan dzi
   *   Recheck every page image for DZI files on the next request.
   */
  public function resetMissingAssetScan(string $asset = 'all'): void {
    $assets = $asset === 'all' ? PageAssetManagement::ASSET_TYPES : [$asset];
    foreach ($assets as $type) {
      if (!in_array($type, PageAssetManagement::ASSET_TYPES, TRUE)) {
        throw new \InvalidArgumentException("Unknown asset [$type]; use dzi, pdf or all.");
      }
      $watermark = PageAssetManagement::getScanWatermark($type);
      PageAssetManagement::resetScan($type);
      $this->logger()->success("Reset the $type scan; it had checked up to file ID $watermark.");
    }
  }

}
