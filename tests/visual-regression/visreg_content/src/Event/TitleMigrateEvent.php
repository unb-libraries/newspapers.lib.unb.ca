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
class TitleMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '0_visreg_content_titles';

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
      TRUE;

      // $row->setSourceProperty('geo_heritage', $heritage);.
      // $country_name = $row->getSourceProperty('country');.

      $address_country = trim($row->getSourceProperty('publication_country'));
      $address_city = trim($row->getSourceProperty('publication_city'));
      $address_administrative_area = trim($row->getSourceProperty('publication_province'));
      $geo_coverage = trim($row->getSourceProperty('geo_coverage'));
      $publisher = trim($row->getSourceProperty('publisher'));
      $issn = trim($row->getSourceProperty('issn'));
      $credit = trim($row->getSourceProperty('credit'));

      $publisher_id = NULL;
      if (!empty($publisher)) {
        $publisher_tid = $this->taxTermExists($publisher, 'name', 'publisher');
        if (!empty($publisher_tid)) {
          $term = Term::load($publisher_tid);
        }
        else {
          $term = Term::create([
            'vid' => 'publisher',
            'name' => $publisher,
          ]);
          $term->save();
        }
        $publisher_id = $term->id();
      }
      $row->setSourceProperty('publisher', $publisher_id);

      $row->setSourceProperty('country_code', $address_country);
      $row->setSourceProperty('province', $address_administrative_area);
      $row->setSourceProperty('city', $address_city);
      $row->setSourceProperty('geo_coverage', $geo_coverage);
      $row->setSourceProperty('issn', $issn);
      $row->setSourceProperty('credit', $credit);
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
