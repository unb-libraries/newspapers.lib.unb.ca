<?php

namespace Drupal\newspapers_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
    date_default_timezone_set('America/Halifax');
    $history_date = date('m') . '-' . date('d');

    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('issue_date', $history_date, 'ENDS_WITH');
    $issue_results = $query->execute();
    $issue_id = (empty($issue_results)) ? NULL : array_rand($issue_results, 1);

    return [
      '#theme' => 'block__this_day_in_nb_history',
      '#history_issue_id' => $issue_id,
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
