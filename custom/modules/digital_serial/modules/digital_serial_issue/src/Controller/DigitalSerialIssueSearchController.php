<?php

namespace Drupal\digital_serial_issue\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a DigitalSerialIssueSearchController object.
 */
class DigitalSerialIssueSearchController {

  const NULL_STRING_PLACEHOLDER = 'LULL';

  /**
   * Get issues matching data.
   */
  public function getMatchingIssues($title_id, $issue_year, $issue_month, $issue_day, $issue_volume, $issue_issue) {
    $date_obj = DrupalDateTime::createFromArray(
      [
        'year' => $this->deNullifyString($issue_year),
        'month' => $this->deNullifyString($issue_month),
        'day' => $this->deNullifyString($issue_day),
      ]
    );
    $formatted_date = $date_obj->format('Y-m-d');

    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $title_id)
      ->condition('issue_date', $formatted_date)
      ->condition('issue_vol', $this->deNullifyString($issue_volume))
      ->condition('issue_issue', $this->deNullifyString($issue_issue));
    $issue_ids = $query->execute();

    return new JsonResponse(
      [
        'data' => $issue_ids,
        'method' => 'GET',
        'status' => 200,
      ]
    );
  }

  /**
   * Gets an entity's issues from a matching year.
   */
  public function getYearIssues($title_id, $issue_year) {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $title_id)
      ->condition('issue_date', "$issue_year-01-01", '>=')
      ->condition('issue_date', "$issue_year-12-31", '<=');
    $issue_ids = $query->execute();

    return new JsonResponse(
      [
        'data' => $issue_ids,
        'method' => 'GET',
        'status' => 200,
      ]
    );
  }

  /**
   * Gets an entity's issues from a matching year.
   */
  public function getTitleIssues($title_id, $issue_year) {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->condition('parent_title', $title_id);
    $issue_ids = $query->execute();

    return new JsonResponse(
      [
        'data' => $issue_ids,
        'method' => 'GET',
        'status' => 200,
      ]
    );
  }

  /**
   * Remove the null string placeholder from a string and replace it with NULL.
   *
   * @param string $data
   *   The string to modify.
   *
   * @return string
   *   The string without the null string placeholder.
   */
  private function deNullifyString($data) {
    if ($data == self::NULL_STRING_PLACEHOLDER) {
      return NULL;
    }
    return $data;
  }

}
