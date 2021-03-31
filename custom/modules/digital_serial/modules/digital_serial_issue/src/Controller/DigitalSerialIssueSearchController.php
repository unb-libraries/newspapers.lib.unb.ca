<?php

namespace Drupal\digital_serial_issue\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a DigitalSerialIssueSearchController object.
 */
class DigitalSerialIssueSearchController {

  /**
   * Get issues matching data.
   */
  public function getMatchingIssues($title_id, $issue_year, $issue_month, $issue_day, $issue_volume, $issue_issue) {
    $date_obj = DrupalDateTime::createFromArray(
      [
        'year' => $issue_year,
        'month' => $issue_month,
        'day' => $issue_day,
      ]
    );
    $formatted_date = $date_obj->format('Y-m-d');

    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $title_id)
      ->condition('issue_date', $formatted_date)
      ->condition('issue_vol', $issue_volume)
      ->condition('issue_issue', $issue_issue);
    $issue_ids = $query->execute();

    return new JsonResponse(
      [
        'data' => $issue_ids,
        'method' => 'GET',
        'status' => 200,
      ]
    );
  }

}
