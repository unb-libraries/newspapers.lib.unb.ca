<?php

namespace Drupal\newspapers_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Defines This Day in History issue id.
 *
 * @Block(
 *   id = "this_day_in_n_b_history",
 *   admin_label = @Translation("This Day in New Brunswick History"),
 * )
 */
class HistoryViewsBlockController extends BlockBase {

  /**
   * Builds and returns the renderable array for this block plugin.
   *
   * If a block should not be rendered because it has no content, then this
   * method must also ensure to return no content: it must then only return an
   * empty array, or an empty array with #cache set (with cacheability metadata
   * indicating the circumstances for it being empty).
   *
   * @return array
   *   A renderable array representing the content of the block.
   *
   * @see \Drupal\block\BlockViewBuilder
   */
  public function build() {
    $date = new DrupalDateTime();
    $date->setTimezone(timezone_open(date_default_timezone_get()));
    $history_date = $date->format('m-d');

    $history_issue_results = \Drupal::entityQuery('digital_serial_issue')
      ->condition('issue_date', $history_date, 'ENDS_WITH')
      ->execute();

    $serial_issue_id = NULL;
    while (count($history_issue_results) > 0) {
      $selected_issue_id = array_rand($history_issue_results, 1);

      $history_page_results = \Drupal::entityQuery('digital_serial_page')
        ->condition('parent_issue', $selected_issue_id)
        ->execute();

      if ($history_page_results) {
        $serial_issue_id = $selected_issue_id;
        break;
      }
      else {
        unset($history_issue_results[$selected_issue_id]);
      }
    }

    return [
      '#theme' => 'block__this_day_in_nb_history',
      '#history_issue_id' => $serial_issue_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Disable caching for this block.
    return 0;
  }

}
