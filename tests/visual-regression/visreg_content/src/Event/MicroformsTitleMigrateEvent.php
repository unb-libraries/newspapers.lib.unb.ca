<?php

namespace Drupal\visreg_content\Event;

use CommerceGuys\Addressing\Country\CountryRepository;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines the migrate event subscriber.
 */
class MicroformsTitleMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '0_visreg_content_microforms_titles';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = ['onPrepareRow', 0];
    return $events;
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    // Only act on rows for this migration.
    if ($migration_id == self::MIGRATION_ID) {

      $address_country = trim($row->getSourceProperty('country'));
      $address_administrative_area = trim($row->getSourceProperty('prov_state'));
      $address_city = trim($row->getSourceProperty('city_town'));

      $row->setSourceProperty('country_code', $address_country);
      $row->setSourceProperty('province', $address_administrative_area);
      $row->setSourceProperty('city', $address_city);

      // Borrowed code from TitleMigrateEvent.
      $first_issue_date_type = $first_issue_verbatim_date = $first_issue_end_date = NULL;
      $first_issue_start_date = trim($row->getSourceProperty('first_issue_start_date'));
      list($first_start_day, $first_start_month, $first_start_year) = explode("/", $first_issue_start_date);
      if (checkdate((int) $first_start_month, (int) $first_start_day, (int) $first_start_year)) {
        $first_issue_end_date = trim($row->getSourceProperty('first_issue_end_date'));
        list($first_end_day, $first_end_month, $first_end_year) = explode("/", $first_issue_end_date);
        if (checkdate((int) $first_end_month, (int) $first_end_day, (int) $first_end_year)) {
          $first_issue_date_type = "approximate";
          $first_issue_verbatim_date = trim($row->getSourceProperty('first_issue_date'));
        }
        else {
          $first_issue_date_type = "exact";
        }
      }
      $first_issue_date_range = $first_issue_start_date . "-" . $first_issue_end_date;
      $row->setSourceProperty('first_issue_date_type', $first_issue_date_type);
      $row->setSourceProperty('first_issue_date_range', $first_issue_date_range);
      $row->setSourceProperty('first_issue_verbatim_date', $first_issue_verbatim_date);

      $last_issue_date_type = $last_issue_end_date = NULL;
      $last_issue_start_date = trim($row->getSourceProperty('last_issue_start_date'));
      $last_issue_verbatim_date = strtolower(trim($row->getSourceProperty('last_issue_date')));
      list($last_start_day, $last_start_month, $last_start_year) = explode("/", $last_issue_start_date);
      if ($last_issue_verbatim_date == "present") {
        $last_issue_date_type = "ongoing";
        $last_issue_verbatim_date = NULL;
      }
      elseif (checkdate((int) $last_start_month, (int) $last_start_day, (int) $last_start_year)) {
        $last_issue_end_date = trim($row->getSourceProperty('last_issue_end_date'));
        list($last_end_day, $last_end_month, $last_end_year) = explode("/", $last_issue_end_date);
        if (checkdate((int) $last_end_month, (int) $last_end_day, (int) $last_end_year)) {
          $last_issue_date_type = "approximate";
          $last_issue_verbatim_date = trim($row->getSourceProperty('last_issue_date'));
        }
        else {
          $last_issue_date_type = "exact";
          $last_issue_verbatim_date = NULL;
        }
      }
      $last_issue_date_range = $last_issue_start_date . "-" . $last_issue_end_date;
      $row->setSourceProperty('last_issue_date_type', $last_issue_date_type);
      $row->setSourceProperty('last_issue_date_range', $last_issue_date_range);
      $row->setSourceProperty('last_issue_verbatim_date', $last_issue_verbatim_date);
    }

  }

  /**
   * Get 2-letter country code for Address w/ CommerceGuys Country Repository.
   *
   * @param string $country
   *   The country name.
   *
   * @return string
   *   The 2-letter country code, default Canada if not found.
   */
  public function getCountryCode($country) {
    $countryRepository = new CountryRepository();
    $countries = $countryRepository->getList();
    $return = array_search($country, $countries);

    return (empty($return)) ? "CA" : $return;
  }

  /**
   * Check if a taxonomy term exists.
   *
   * @param string $value
   *   The name of the term.
   * @param string $field
   *   The field to match when validating.
   * @param string $vocabulary
   *   The vid to match.
   *
   * @return mixed
   *   Contains an INT of the tid if exists, FALSE otherwise.
   */
  public function taxTermExists($value, $field, $vocabulary) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);
    $query->condition($field, $value);

    dump($query);

    $tids = $query->execute();
    if (!empty($tids)) {
      foreach ($tids as $tid) {
        return $tid;
      }
    }
    return FALSE;
  }

}
