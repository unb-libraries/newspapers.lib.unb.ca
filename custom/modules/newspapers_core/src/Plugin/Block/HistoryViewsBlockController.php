<?php

namespace Drupal\newspapers_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
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
    return [
      '#theme' => 'block__this_day_in_nb_history',
      '#history_issue_id' => $this->getHistoryIssueId(),
    ];
  }

  /**
   * Returns the issue chosen for today, choosing one on the first request.
   *
   * The choice is kept apart from the block's render cache, which the view
   * inside it invalidates whenever the pages index changes.
   *
   * @return int|null
   *   The issue ID, or NULL if no issue with pages was published on this day.
   */
  protected function getHistoryIssueId() {
    $cid = 'newspapers_core:this_day_in_history:' . _newspapers_core_today();
    $cache = \Drupal::cache()->get($cid);
    if ($cache) {
      return $cache->data;
    }
    $serial_issue_id = $this->chooseHistoryIssueId();
    \Drupal::cache()->set(
      $cid,
      $serial_issue_id,
      \Drupal::time()->getRequestTime() + _newspapers_core_seconds_until_tomorrow(),
      [NEWSPAPERS_CORE_DAILY_CACHE_TAG]
    );
    return $serial_issue_id;
  }

  /**
   * Picks a random issue with pages, published on this day in any year.
   *
   * @return int|null
   *   The issue ID, or NULL if there is none.
   */
  protected function chooseHistoryIssueId() {
    $date = new DrupalDateTime();
    $date->setTimezone(timezone_open(date_default_timezone_get()));
    $history_date = $date->format('m-d');

    $history_issue_results = \Drupal::entityQuery('digital_serial_issue')
      ->accessCheck(TRUE)
      ->condition('issue_date', $history_date, 'ENDS_WITH')
      ->execute();

    $serial_issue_id = NULL;
    while (count($history_issue_results) > 0) {
      $selected_issue_id = array_rand($history_issue_results, 1);

      $history_page_results = \Drupal::entityQuery('digital_serial_page')
        ->accessCheck(TRUE)
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

    return $serial_issue_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [NEWSPAPERS_CORE_DAILY_CACHE_TAG]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // A new issue is chosen each day. Cron also invalidates the daily tag, as
    // the anonymous page cache ignores max-age.
    return _newspapers_core_seconds_until_tomorrow();
  }

}
