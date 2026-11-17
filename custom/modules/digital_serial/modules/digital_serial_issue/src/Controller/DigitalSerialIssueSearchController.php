<?php

namespace Drupal\digital_serial_issue\Controller;

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
    $date_range = $this->getIssueDateRange($issue_year, $issue_month, $issue_day);
    if ($date_range === FALSE) {
      $issue_ids = [];
    }
    else {
      $query = \Drupal::entityQuery('digital_serial_issue')
        ->accessCheck(TRUE)
        ->condition('parent_title', $title_id)
        ->condition('issue_vol', $this->deNullifyString($issue_volume))
        ->condition('issue_issue', $this->deNullifyString($issue_issue));
      if ($date_range !== NULL) {
        [$start, $end] = $date_range;
        if ($start === $end) {
          $query->condition('issue_date', $start);
        }
        else {
          $query->condition('issue_date', $start, '>=')
            ->condition('issue_date', $end, '<=');
        }
      }
      $issue_ids = $query->execute();
    }

    return new JsonResponse(
      [
        'data' => $issue_ids,
        'method' => 'GET',
        'status' => 200,
      ]
    );
  }

  /**
   * Builds the issue_date range to match from URL date parts.
   *
   * Month and day may be zero-padded ('05') or not ('5'). A part given as the
   * null string placeholder is unspecified, which widens the range: a missing
   * day matches the whole month, a missing month and day the whole year, and a
   * missing year any date.
   *
   * @param string $year
   *   The year from the URL.
   * @param string $month
   *   The month from the URL.
   * @param string $day
   *   The day from the URL.
   *
   * @return array|null|false
   *   An array of the first and last Y-m-d dates to match, NULL to match any
   *   date, or FALSE if the parts are invalid or the combination is ambiguous
   *   (a day without a month, or a month without a year).
   */
  private function getIssueDateRange($year, $month, $day) {
    $year = $this->normalizeDatePart($year);
    $month = $this->normalizeDatePart($month);
    $day = $this->normalizeDatePart($day);

    if ($year === FALSE || $month === FALSE || $day === FALSE) {
      return FALSE;
    }
    if ($year === NULL) {
      return ($month === NULL && $day === NULL) ? NULL : FALSE;
    }
    if ($year < 1 || $year > 9999) {
      return FALSE;
    }
    if ($month === NULL) {
      if ($day !== NULL) {
        return FALSE;
      }
      return [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)];
    }
    if ($month < 1 || $month > 12) {
      return FALSE;
    }
    if ($day === NULL) {
      $first = sprintf('%04d-%02d-01', $year, $month);
      return [$first, (new \DateTimeImmutable($first))->format('Y-m-t')];
    }
    if (!checkdate($month, $day, $year)) {
      return FALSE;
    }
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    return [$date, $date];
  }

  /**
   * Normalizes one numeric date part from the URL.
   *
   * @param string $value
   *   The value from the URL.
   *
   * @return int|null|false
   *   The value as an integer, NULL if it is the null string placeholder or
   *   empty, or FALSE if it is not a string of digits.
   */
  private function normalizeDatePart($value) {
    $value = (string) $value;
    if ($value === '' || $value === self::NULL_STRING_PLACEHOLDER) {
      return NULL;
    }
    if (!ctype_digit($value)) {
      return FALSE;
    }
    return (int) $value;
  }

  /**
   * Gets an entity's issues from a matching year.
   */
  public function getYearIssues($title_id, $issue_year) {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->accessCheck(TRUE)
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
   * Gets an entity's issues from a title.
   */
  public function getTitleIssues($title_id) {
    $query = \Drupal::entityQuery('digital_serial_issue')
      ->accessCheck(TRUE)
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
   * Gets an issue's parent title.
   */
  public function getIssueTitleId($issue_id) {
    $issue = \Drupal::entityTypeManager()
      ->getStorage('digital_serial_issue')
      ->load($issue_id);

    if ($issue == NULL) {
      $title_id = NULL;
    } else {
      $title_id = $issue->getParentTitleId();
    }

    return new JsonResponse(
      [
        'data' => $title_id,
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
