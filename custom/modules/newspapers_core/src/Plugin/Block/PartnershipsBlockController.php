<?php

namespace Drupal\newspapers_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Partnerships block.
 *
 * @Block(
 *   id = "nbhnp_partnerships",
 *   admin_label = @Translation("NBHNP Partnerships"),
 *   category = @Translation("UNB Libraries"),
 * )
 */
class PartnershipsBlockController extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [],
      ],
      '#value' => $this->getValue(),
    ];
  }

  /**
   * Gets the Partnership section.
   *
   * @return string
   *   The html structure for NBHNP Partnerships.
   */
  protected function getValue() {
    $html = '
    <div class="d-flex flex-column flex-sm-row mx-0 mx-sm-5">
      <div class="mx-5 mb-3 mb-sm-0">
        <a href="https://lib.unb.ca"><img src="/themes/custom/newspapers_lib_unb_ca/src/img/unb_libraries_logo.png"
        alt="UNB Libraries"/></a>
      </div>
      <div class="mx-5 my-3 my-sm-0">
        <a href="https://archives.gnb.ca/Archives"><img src="/themes/custom/newspapers_lib_unb_ca/src/img/gnb_logo.png"
        alt="Government of New Brunswick, Canada|Gouvernement du Nouveau-Brunswick, Canada"/></a>
      </div>
      <div class="mx-5 my-3 my-sm-0">
        <a href="https://www.canbarchives.ca"><img src="/themes/custom/newspapers_lib_unb_ca/src/img/canb_logo.png"
        alt="Council of Archives New Brunswick|Conseil des archives Nouveau-Brunswick"/></a>
        </div>
    </div>
    ';

    return $html;
  }

}
