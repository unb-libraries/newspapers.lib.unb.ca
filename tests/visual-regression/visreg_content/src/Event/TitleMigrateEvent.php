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

}
