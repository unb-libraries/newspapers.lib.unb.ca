<?php

namespace Drupal\digital_serial_issue\Controller;

use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a DigitalSerialIssueSearchController object.
 */
class DigitalSerialIssueSearchController {

  const NULL_STRING_PLACEHOLDER = 'LULL';

  /**
   * Get issues matching a title, date, volume and issue.
   *
   * Volume and issue may also be given as the 'volume' and 'issue' query
   * parameters, which take precedence over the path. Use them for values that
   * contain '/', such as issue '39/3=13', which cannot be a path segment.
   *
   * The null string placeholder as volume or issue matches only issues where
   * that field is empty (NULL or ''); it is not a wildcard.
   */
  public function getMatchingIssues(Request $request, $title_id, $issue_year, $issue_month, $issue_day, $issue_volume = NULL, $issue_issue = NULL) {
    $issue_volume = $request->query->get('volume', $issue_volume);
    $issue_issue = $request->query->get('issue', $issue_issue);
    if ($issue_volume === NULL || $issue_volume === '' || $issue_issue === NULL || $issue_issue === '') {
      return new JsonResponse(
        [
          'error' => 'Volume and issue are required, in the path or as the volume and issue query parameters. Use ' . self::NULL_STRING_PLACEHOLDER . ' for an empty value.',
          'method' => 'GET',
          'status' => 400,
        ],
        400
      );
    }

    $date_condition = $this->getIssueDateCondition($issue_year, $issue_month, $issue_day);
    if ($date_condition === FALSE) {
      $issue_ids = [];
    }
    else {
      $query = \Drupal::entityQuery('digital_serial_issue')
        ->accessCheck(TRUE)
        ->condition('parent_title', $title_id);
      $this->addEmptyableCondition($query, 'issue_vol', $issue_volume);
      $this->addEmptyableCondition($query, 'issue_issue', $issue_issue);
      if ($date_condition !== NULL) {
        [$value, $operator] = $date_condition;
        $query->condition('issue_date', $value, $operator);
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
   * Adds a condition on a string field that may be empty.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to add the condition to.
   * @param string $field
   *   The field name.
   * @param string $value
   *   The value to match. The null string placeholder matches a NULL or empty
   *   field; comparing to NULL or '' with '=' would never match a NULL column.
   */
  private function addEmptyableCondition(QueryInterface $query, $field, $value) {
    if ($value === self::NULL_STRING_PLACEHOLDER) {
      $query->condition(
        $query->orConditionGroup()
          ->notExists($field)
          ->condition($field, '')
      );
    }
    else {
      $query->condition($field, $value);
    }
  }

  /**
   * Builds the issue_date condition to match from URL date parts.
   *
   * Month and day may be zero-padded ('05') or not ('5'). Each part given as
   * the null string placeholder is independently "don't filter on this part"
   * (option A): '1907/LULL/16' matches the 16th of any month in 1907, and
   * 'LULL/8/16' matches 16 August in any year. A date with no placeholder must
   * be a real calendar date; with placeholders, each given part must be valid
   * for at least one date (so 'LULL/2/29' is accepted, '1907/2/30' is not).
   *
   * @param string $year
   *   The year from the URL.
   * @param string $month
   *   The month from the URL.
   * @param string $day
   *   The day from the URL.
   *
   * @return array|null|false
   *   An array of the value and operator for the issue_date condition, NULL
   *   to match any date, or FALSE if the parts are invalid.
   */
  private function getIssueDateCondition($year, $month, $day) {
    $year = $this->normalizeDatePart($year);
    $month = $this->normalizeDatePart($month);
    $day = $this->normalizeDatePart($day);

    if ($year === FALSE || $month === FALSE || $day === FALSE) {
      return FALSE;
    }
    if ($year === NULL && $month === NULL && $day === NULL) {
      return NULL;
    }
    if ($year !== NULL && ($year < 1 || $year > 9999)) {
      return FALSE;
    }
    if ($month !== NULL && ($month < 1 || $month > 12)) {
      return FALSE;
    }
    if ($day !== NULL) {
      // Year 2000 is a leap year, so an unknown year allows 29 February.
      $max_day = $month === NULL ? 31 : (int) date('t', mktime(0, 0, 0, $month, 1, $year ?? 2000));
      if ($day < 1 || $day > $max_day) {
        return FALSE;
      }
    }

    // issue_date is stored as a Y-m-d string, so each part has a fixed
    // position and '%' cannot match across into another part.
    $year = $year === NULL ? '%' : sprintf('%04d', $year);
    $month = $month === NULL ? '%' : sprintf('%02d', $month);
    $day = $day === NULL ? '%' : sprintf('%02d', $day);
    $value = "$year-$month-$day";
    return [$value, strpos($value, '%') === FALSE ? '=' : 'LIKE'];
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

}
