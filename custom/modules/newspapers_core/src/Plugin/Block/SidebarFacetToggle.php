<?php

namespace Drupal\newspapers_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Toggle Facets' button block for use in sidebar.
 *
 * @Block(
 *   id = "sidebar_facet_toggle",
 *   admin_label = @Translation("Sidebar Facet Toggle"),
 *   category = @Translation("UNB Libraries"),
 * )
 */
class SidebarFacetToggle extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $html = '
        <button class="btn btn-block btn-sm btn-light border d-md-none" data-toggle="collapse" data-target="#sidebarFacets"
         aria-expanded="true" aria-controls="sidebarFacets" type="button">Toggle search filters</button>';

    $render_array = [
      '#type' => 'markup',
      '#markup' => $html,
      '#allowed_tags' => ['button'],
    ];

    return $render_array;
  }

}
