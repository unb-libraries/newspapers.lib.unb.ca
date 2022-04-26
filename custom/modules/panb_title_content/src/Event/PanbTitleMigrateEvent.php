<?php

namespace Drupal\panb_title_content\Event;

use Drupal\migrate\Event\MigrateEvents as CoreMigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines the PANB migrate event subscriber.
 */
class PanbTitleMigrateEvent implements EventSubscriberInterface {

  const MIGRATION_ID = '0_a_panb_title_content';
  const SRC_FULL_TITLE_RECORD_FILE = '/app/html/modules/custom/panb_title_content/data/csv/z_panb_full_title_metadata.csv';
  const SRC_FULL_TITLE_RECORD_ID_COLUMN = '0';
  const SRC_FULL_TITLE_RECORD_ISFRENCH_COLUMN = '23';
  const SRC_MICROFILMED_BY_FILE = '/app/html/modules/custom/panb_title_content/data/csv/z_panb_microfilmed_by.csv';
  const SRC_MICROFILMED_BY_INSTITUTION_CODE_COLUMN = '2';
  const SRC_MICROFILMED_BY_PANB_ID_COLUMN = '1';
  const SRC_PUBLISHER_FILE = '/app/html/modules/custom/panb_title_content/data/csv/z_panb_publishers.csv';
  const SRC_PUBLISHER_ID_COLUMN = '0';
  const SRC_PUBLISHER_JUNCTION_FILE = '/app/html/modules/custom/panb_title_content/data/csv/z_panb_publisher_junction.csv';
  const SRC_PUBLISHER_JUNCTION_PANB_ID_COLUMN = '2';
  const SRC_PUBLISHER_JUNCTION_PUBLISHER_ID_COLUMN = '1';

  /**
   * The current row.
   *
   * @var \Drupal\migrate\Row
   */
  protected Row $curRow;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW][] = ['onPrepareRow', 0];
    $events[CoreMigrateEvents::POST_ROW_SAVE][] = ['onPostRowSave', 0];
    return $events;
  }

  /**
   * Reacts to a new migration row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   *
   * @throws \Exception
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) : void {
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    // Only act on rows for this migration.
    if ($migration_id == self::MIGRATION_ID) {
      $this->curRow = $event->getRow();
      $this->setCreditField();
      $this->setDescriptionField();
      $this->setLanguageField();
      $this->setFrequencyField();
      $this->setPublisherField();
      $this->setMicroFilmedByField();
      $this->setDatesFields();
    }
  }

  /**
   * Constructs/sets the title's credit data for a publication migration.
   *
   * @throws \Exception
   */
  protected function setCreditField() : void {
    $credit_lines = [];

    // PANB id.
    $panb_id = trim($this->curRow->getSourceProperty('ID'));
    if (!empty($panb_id)) {
      $credit_lines[] = "Imported from PANB Newspaper Directory: Key = $panb_id";
    }

    // Harper.
    $harper_number = trim($this->curRow->getSourceProperty('Harper #'));
    if (!empty($harper_number)) {
      $credit_lines[] = "Harper #: $harper_number";
    }

    // Write out.
    if (!empty($credit_lines)) {
      $this->curRow->setSourceProperty(
        'credit_processed',
        implode("\n",
          $credit_lines
        )
      );
    }
  }

  /**
   * Constructs/sets the title's description data for a publication migration.
   *
   * @throws \Exception
   */
  protected function setDescriptionField() : void {
    $description_lines = [];
    $note_content = trim($this->curRow->getSourceProperty('Note'));
    if (!empty($note_content)) {
      $description_lines[] = $note_content;
    }
    $dates_questionable = trim($this->curRow->getSourceProperty('Dates Questionable'));
    if (!empty($dates_questionable) && trim($dates_questionable) == 'Yes') {
      $description_lines[] = 'Dates Questionable.';
    }
    if (!empty($description_lines)) {
      $this->curRow->setSourceProperty(
        'description_processed',
        implode(
          "\n",
          $description_lines
        )
      );
    }
  }

  /**
   * Constructs/sets the title's frequency data for a publication migration.
   *
   * The mappings provided are determined from the unique values in the NBNP
   * list at time of migration.
   *
   * @throws \Exception
   */
  protected function setFrequencyField() : void {
    $map_fields = [
      '2/monthly' => 'Semimonthly',
      '2/weekly' => 'Semiweekly',
      '2/yearly' => 'Semiannual',
      '3/weekly' => 'Three times a week',
      'biweekly' => 'Biweekly',
      'daily' => 'Daily',
      'irregular' => 'No determinable frequency',
      'monthly' => 'Monthly',
      'semi-weekly' => 'Semiweekly',
      'unknown' => 'Unknown',
      'varies' => 'Frequency varies',
      'weekly' => 'Weekly',
    ];
    $interval_content = trim($this->curRow->getSourceProperty('Interval'));
    $map_key = '';
    if (!empty($interval_content)) {
      if (array_key_exists($interval_content, $map_fields)) {
        $map_key = $interval_content;
      }
    }
    if (empty($map_key)) {
      $map_key = 'unknown';
    }
    $this->curRow->setSourceProperty('frequency_processed', $map_fields[$map_key]);
  }

  /**
   * Constructs/sets the title's publisher data for a publication migration.
   *
   * @throws \Exception
   */
  protected function setPublisherField() : void {
    $panb_id = trim($this->curRow->getSourceProperty('ID'));
    $raw_publisher_names = $this->getRawNewspaperPublisherNames($panb_id);
    $publishers = [];

    foreach ($raw_publisher_names as $raw_publisher_name) {
      if (!empty($raw_publisher_name)) {
        $publisher_tids = $this->getMatchingTaxTerms(
          $raw_publisher_name,
          'name',
          'publisher'
        );
        if (!empty($publisher_tids)) {
          $term = Term::load(reset($publisher_tids));
        }
        else {
          $term = Term::create([
            'vid' => 'publisher',
            'name' => $raw_publisher_name,
          ]);
          $term->save();
        }
        $publishers[] = $term->id();
      }
    }
    $this->curRow->setSourceProperty('publishers', $publishers);
  }

  /**
   * Constructs/sets the title's language data for a publication migration.
   *
   * @throws \Exception
   */
  protected function setLanguageField() : void {
    $panb_id = trim($this->curRow->getSourceProperty('ID'));
    if (!empty($panb_id)) {
      $this->curRow->setSourceProperty(
        'language_processed',
        $this->getLanguageValueFromId($panb_id)
      );
    }
  }

  /**
   * Determines a title's language code from data provided by PANB.
   *
   * The language value depends on a bool column 'isfrench', with the
   * presumption that anything else is english.
   *
   * @param string $panb_id
   *   The unique PAND id identifying the publication.
   *
   * @return string
   *   The ISO 639-1 language code of the title's content.
   */
  protected function getLanguageValueFromId(string $panb_id) : string {
    $full_title_metadata = array_map(
      'str_getcsv',
      file(self::SRC_FULL_TITLE_RECORD_FILE)
    );

    foreach ($full_title_metadata as $title_metadata) {
      if ($title_metadata[self::SRC_FULL_TITLE_RECORD_ID_COLUMN] == $panb_id) {
        if ($title_metadata[self::SRC_FULL_TITLE_RECORD_ISFRENCH_COLUMN] == 'True') {
          return 'fr';
        }
        else {
          return 'en';
        }
      }
    }
    return '';
  }

  /**
   * Determines a title's publisher names from data provided by PANB.
   *
   * The publisher names are determined by parsing an exported relational table
   * cross-referenced with an exported publisher identification. See CSV files.
   *
   * @param string $panb_id
   *   The unique PAND id identifying the publication.
   *
   * @return array
   *   An array of publisher names. Empty if no publishers are found for the ID.
   */
  protected function getRawNewspaperPublisherNames(string $panb_id) : array {
    $publisher_csv = array_map(
      'str_getcsv',
      file(self::SRC_PUBLISHER_FILE)
    );
    $publisher_junction_csv = array_map(
      'str_getcsv',
      file(self::SRC_PUBLISHER_JUNCTION_FILE)
    );
    $found_publisher_ids = [];
    $publishers = [];
    foreach ($publisher_junction_csv as $junction_item) {
      if (
        isset($junction_item[self::SRC_PUBLISHER_JUNCTION_PANB_ID_COLUMN]) &&
        $junction_item[self::SRC_PUBLISHER_JUNCTION_PANB_ID_COLUMN] == $panb_id
      ) {
        $found_publisher_ids[] = $junction_item[self::SRC_PUBLISHER_JUNCTION_PUBLISHER_ID_COLUMN];
      }
    }
    foreach ($found_publisher_ids as $publisher_id) {
      foreach ($publisher_csv as $publisher_data) {
        if (
          isset($publisher_data[self::SRC_PUBLISHER_ID_COLUMN]) &&
          $publisher_data[self::SRC_PUBLISHER_ID_COLUMN] == $publisher_id
        ) {
          $publishers[] = $publisher_data[self::SRC_PUBLISHER_JUNCTION_PUBLISHER_ID_COLUMN];
        }
      }
    }
    return $publishers;
  }

  /**
   * Checks if a taxonomy term exists in a vocabulary.
   *
   * @param string $value
   *   The name of the term.
   * @param string $field
   *   The field to match when validating.
   * @param string $vocabulary
   *   The vid to match.
   *
   * @return array
   *   An array of matching TIDs.
   */
  public function getMatchingTaxTerms(
    string $value,
    string $field,
    string $vocabulary
  ) : array {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocabulary)
      ->condition($field, $value);

    return $query->execute();
  }

  /**
   * Constructs/sets the title's 'microfilmed by' for a publication migration.
   *
   * @throws \Exception
   */
  protected function setMicroFilmedByField() : void {
    $panb_id = trim($this->curRow->getSourceProperty('ID'));
    $institution_values = [];
    $microfilmed_by_codes = $this->getMicrofilmedByCode($panb_id);
    foreach ($microfilmed_by_codes as $microfilmed_by_code) {
      $tids = $this->getInstitutionTermIdsByPanbCode($microfilmed_by_code);
      $institution_values = array_merge($institution_values, $tids);
    }
    if (!empty($institution_values)) {
      $this->curRow->setSourceProperty(
        'microfilmed_by_institutions',
        $institution_values
      );
    }
  }

  /**
   * Determines a title's 'microfilmed-by' institution code from PANB data.
   *
   * @param string $panb_id
   *   The unique PAND id identifying the publication.
   *
   * @return array
   *   The matching PANB institution codes.
   */
  protected function getMicrofilmedByCode(string $panb_id) : array {
    $microfilmed_by_csv = array_map(
      'str_getcsv',
      file(self::SRC_MICROFILMED_BY_FILE)
    );
    $microfilmed_by = [];
    foreach ($microfilmed_by_csv as $microfilmed_by_statement) {
      if (
        $microfilmed_by_statement[self::SRC_MICROFILMED_BY_PANB_ID_COLUMN] == $panb_id
      ) {
        if (
          !empty(
            $microfilmed_by_statement[self::SRC_MICROFILMED_BY_INSTITUTION_CODE_COLUMN]
          )
        ) {
          $microfilmed_by[] = $microfilmed_by_statement[self::SRC_MICROFILMED_BY_INSTITUTION_CODE_COLUMN];
        }
      }
    }
    return $microfilmed_by;
  }

  /**
   * Returns the institution term ids that match the PANB institution code.
   *
   * @param string $panb_code
   *   The PANB institution code to match when selecting terms.
   *
   * @return array
   *   An array of term IDs that match the code.
   */
  protected function getInstitutionTermIdsByPanbCode(string $panb_code) : array {
    return $this->getMatchingTaxTerms(
      $panb_code,
      'field_panb_code',
      'institution'
    );
  }

  /**
   * Constructs/sets the title's date data for a publication migration.
   *
   * @throws \Exception
   */
  protected function setDatesFields() : void {
    $title_start_day = trim($this->curRow->getSourceProperty('Start Day'));
    $title_start_month = trim($this->curRow->getSourceProperty('Start Month'));
    $title_start_year = trim($this->curRow->getSourceProperty('Start Year'));
    $title_end_day = trim($this->curRow->getSourceProperty('End Day'));
    $title_end_month = trim($this->curRow->getSourceProperty('End Month'));
    $title_end_year = trim($this->curRow->getSourceProperty('End Year'));

    // We cannot approximate a date without the year value.
    if (!empty($title_start_year)) {
      $start_date_data = $this->getDateFieldData(
        $title_start_year,
        $title_start_month,
        $title_start_day
      );
      if (!empty($start_date_data)) {
        $this->curRow->setSourceProperty(
          'first_issue_date_type',
          $start_date_data['date_type']
        );
        $this->curRow->setSourceProperty(
          'first_issue_date_range',
          $start_date_data['date_range']
        );
        $this->curRow->setSourceProperty(
          'first_issue_verbatim_date',
          $start_date_data['verbatim_date']
        );
      }
    }

    // Special case : No data in end date, with values in start date -> ongoing.
    if (
      empty($title_end_year) &&
      empty($title_end_month) &&
      empty($title_end_day) &&
      !empty($start_date_data['date_type'])
    ) {
      $this->curRow->setSourceProperty('last_issue_date_type', 'ongoing');
    }
    elseif (!empty($title_end_year)) {
      $end_date_data = $this->getDateFieldData(
        $title_end_year,
        $title_end_month,
        $title_end_day
      );
      if (!empty($end_date_data)) {
        $this->curRow->setSourceProperty(
          'last_issue_date_type',
          $end_date_data['date_type']
        );
        $this->curRow->setSourceProperty(
          'last_issue_date_range',
          $end_date_data['date_range']
        );
        $this->curRow->setSourceProperty(
          'last_issue_verbatim_date',
          $end_date_data['verbatim_date']
        );
      }
    }
  }

  /**
   * Builds a date data set compatible with our approx/date range construct.
   *
   * @param string $year_string
   *   The year to use in the structure.
   * @param string $month_string
   *   The month to use in the structure.
   * @param string $day_string
   *   The day to use in the structure.
   *
   * @return array
   *   An associative array of data, empty if unable to build a reasonable date.
   *
   * @throws \Exception
   */
  protected function getDateFieldData(
    string $year_string,
    string $month_string,
    string $day_string
  ) : array {
    // Case 1 : Date is valid!
    if (checkdate((int) $month_string, (int) $day_string, (int) $year_string)) {
      $date_type = "exact";
      $date = new \DateTime($year_string . '-' . $month_string . '-' . $day_string);
      $date_string = $date->format('Y-m-d');
      $date_range = $date_string . "|";
      $verbatim_date = $date_string;
    }
    // Case 2 : All fields provided, invalid date data detected.
    elseif (
      !empty($year_string) &&
      !empty($month_string) &&
      !empty($day_string)
    ) {
      return [];
    }
    // Case 3 : Only year provided.
    elseif (
      !empty($year_string) &&
      empty($month_string) &&
      empty($day_string)
    ) {
      $date_type = "approximate";
      $date_range = "$year_string-01-01|$year_string-12-31";
      $verbatim_date = $year_string;
    }
    // Case 4 : Year and month provided.
    elseif (
      !empty($year_string) &&
      !empty($month_string)
      && empty($day_string)
    ) {
      $date_type = "approximate";
      $last_day_of_month = date(
        't',
        mktime(0, 0, 0, $month_string, 1, $year_string)
      );
      $month_value_padded = str_pad(
        $month_string,
        2,
        '0',
        STR_PAD_LEFT
      );
      $date_range = "$year_string-$month_value_padded-01|$year_string-$month_value_padded-$last_day_of_month";
      $verbatim_date = "$month_value_padded-$year_string";
    }
    // Case 5 : Provided data is wildly confusing.
    else {
      return [];
    }
    return [
      'date_type' => $date_type,
      'date_range' => $date_range,
      'verbatim_date' => $verbatim_date,
    ];
  }

  /**
   * Reacts to the save of a completed migration row.
   *
   * I am not sure why this is necessary, but the date ranges do not appear to
   * update correctly without this second save - JS.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The migrate post row save event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) : void {
    $migration = $event->getMigration();
    $migration_id = $migration->id();

    // Only act on saves for this migration.
    if ($migration_id == self::MIGRATION_ID) {
      $id = $event->getDestinationIdValues();
      $id = reset($id);
      $node = Node::load($id);
      $node->save();
    }
  }

}
