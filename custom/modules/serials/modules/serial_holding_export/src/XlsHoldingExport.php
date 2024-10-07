<?php

namespace Drupal\serial_holding_export;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deliver user holding records as an XLSX file.
 */
class XlsHoldingExport {

  use StringTranslationTrait;

  const COLUMN_MAPPING_CONFIG_KEY = 'serial_holding_export.export';
  const ERROR_RESPONSE_CODE = 201;
  const HOLDING_TAXONOMY_TYPE_VID = 'serial_holding_types';
  const SUCCESS_RESPONSE_CODE = 200;

  /**
   * We only export holdings from these institutions (UNB Libraries!).
   */
  const HOLDINGS_EXPORT_INSTITUTION_IDS = [
    626,
    652,
    654,
  ];

  /**
   * The response object.
   *
   * @var \Symfony\Component\HttpFoundation\Response
   */
  public $response = NULL;

  /**
   * The column mapping to use when formatting output.
   *
   * @var array
   */
  private $columnMapping = [];

  /**
   * The configuration entity to use.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config = NULL;

  /**
   * The holdings to output.
   *
   * @var \Drupal\serial_holding\Entity\SerialHoldingInterface[]
   */
  private $holdings = [];

  /**
   * The filename to use when serving the file.
   *
   * @var string
   */
  private $outputFilename = NULL;

  /**
   * The current XLSX active worksheet.
   *
   * @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
   */
  private $sheet = NULL;

  /**
   * The current XLSX spreadsheet.
   *
   * @var \PhpOffice\PhpSpreadsheet\Spreadsheet
   */
  private $spreadsheet = NULL;

  /**
   * The temporary filename to use for serving the file.
   *
   * @var string
   */
  private $tempFilename = NULL;

  /**
   * The holding type.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private $type = NULL;

  /**
   * Constructor.
   *
   * @param string $type
   *   The type of holding to export.
   */
  protected function __construct($type) {
    $this->setType($type);
    $this->validateType($type);

    if (empty($this->response)) {
      $this->setHoldings();
      $this->validateHoldings();
    }

    if (empty($this->response)) {
      $this->setConfig();
      $this->exportHoldings();
    }
  }

  /**
   * Get an export object.
   *
   * @param string $type
   *   The type of holding to export.
   *
   * @return $this
   *   The constructed XlsHoldingExport object.
   */
  public static function exportFromType($type) {
    return new static($type);
  }

  /**
   * Set the current holding type by providing the name.
   *
   * @param string $type_name
   *   The type of holding to export.
   */
  private function setType($type_name) {
    $type_term_result = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(
        [
          'vid' => self::HOLDING_TAXONOMY_TYPE_VID,
          'name' => $type_name,
        ]
      );
    if (!empty(array_values($type_term_result)[0])) {
      $this->type = array_values($type_term_result)[0];
    }
  }

  /**
   * Validate the currently set holding type.
   */
  private function validateType($type_string) {
    if (!$this->isValidType()) {
      $this->setInvalidTypeResponse($type_string);
    }
  }

  /**
   * Determine if a current type is set and valid.
   */
  private function isValidType() {
    if (!empty($this->type)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set the response indicating that an invalid type was provided.
   */
  private function setInvalidTypeResponse($type_string) {
    $this->response = $this->generateErrorResponse(
      $this->t(
        'The holding type [@type] is invalid.',
        [
          '@type' => $type_string,
        ]
      )
    );
  }

  /**
   * Generate an generic error response.
   *
   * @param string $message
   *   The error message to include in the response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  private function generateErrorResponse($message = 'Error') {
    $response = new Response();
    $valid_types = implode(", ", $this->getValidTypes());
    $response->setContent("<html><body><h1>Export Error</h1><p>$message. Valid holding types: $valid_types</p></body></html>");
    $response->setStatusCode(self::ERROR_RESPONSE_CODE);
    $response->headers->set('Content-Type', 'text/html');
    return $response;
  }

  /**
   * Get the valid holding types.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The valid holding types.
   */
  private function getValidTypes() {
    $types = [];
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(
        [
          'vid' => self::HOLDING_TAXONOMY_TYPE_VID,
        ]
      );
    foreach ($terms as $term) {
      $types[] = $term->getName();
    }
    return $types;
  }

  /**
   * Populate the holdings to be exported.
   */
  private function setHoldings() {
    $this->holdings = \Drupal::entityTypeManager()
      ->getStorage('serial_holding')
      ->loadByProperties(
        [
          'holding_type' => $this->type->id(),
        ]
      );
  }

  /**
   * Validate the holdings list before export.
   */
  private function validateHoldings() {
    if (empty($this->holdings)) {
      $this->setNoHoldingsResponse();
    }
  }

  /**
   * Set the response indicating no holdings for a type exist.
   */
  private function setNoHoldingsResponse() {
    $this->response = $this->generateErrorResponse(
      $this->t(
        'The holding type [@type] has no holdings.',
        [
          '@type' => $this->type->getName(),
        ]
      )
    );
  }

  /**
   * Set the config from the Drupal object.
   */
  private function setConfig() {
    $this->config = \Drupal::config(self::COLUMN_MAPPING_CONFIG_KEY);
  }

  /**
   * Export the holdings to an XLSX response.
   */
  private function exportHoldings() {
    $this->initSpreadSheet();
    $this->setColumnMappings();
    $this->setSpreadsheetTitleRow();
    $this->setSpreadsheetHoldingsData();
    $this->writeTempFile();
    $this->setSuccessResponse();
  }

  /**
   * Initialize a new spreadsheet object.
   */
  private function initSpreadSheet() {
    $this->spreadsheet = new Spreadsheet();
    $this->sheet = $this->spreadsheet->getActiveSheet();
  }

  /**
   * Set the column mapping from the type's config.
   */
  private function setColumnMappings() {
    $config_data = $this->config->get('holding_types');
    $type_data = $config_data[$this->type->getName()];
    $this->columnMapping = $type_data['column_mapping'];
  }

  /**
   * Populate the active spreadsheet title row with column mapping titles.
   */
  private function setSpreadsheetTitleRow() {
    $titles = [];
    foreach ($this->columnMapping as $column_key => $map_function) {
      $titles[] = $column_key;
    }
    $this->sheet->fromArray(
      $titles,
      NULL,
      'A1'
    );
  }

  /**
   * Populate the active spreadsheet the data from the active holdings.
   */
  private function setSpreadsheetHoldingsData() {
    $config_data = $this->config->get('holding_types');
    $type_data = $config_data[$this->type->getName()];

    $spreadsheet_array = [];
    foreach ($this->holdings as $holding) {
      $parent_publication = $holding->getParentTitle();
      $institution_id = $holding->getInstitution()->id();

      if (
        !$holding->isPublished() ||
        empty($parent_publication) ||
        !$parent_publication->isPublished() ||
        empty($institution_id) ||
        !in_array($institution_id, self::HOLDINGS_EXPORT_INSTITUTION_IDS) ||
        $holding->isDigitalHoldingButDigitalTitleHasNoIssues()
      ) {
        continue;
      }

      $row = [];
      $formatter = HoldingExportFormatter::create($holding, $parent_publication, $type_data);
      foreach ($this->columnMapping as $map_key => $formatter_method) {
        if (method_exists($formatter, $formatter_method)) {
          $row[] = $formatter->$formatter_method();
        }
        else {
          $row[] = NULL;
        }
      }
      $spreadsheet_array[] = $row;
    }

    $this->sheet
      ->fromArray(
        $spreadsheet_array,
        NULL,
        'A2'
      );
  }

  /**
   * Write the active spreadsheet to a temporary file.
   */
  private function writeTempFile() {
    $this->tempFilename = tempnam(sys_get_temp_dir(), 'xlsholdingexport');
    $writer = new Xlsx($this->spreadsheet);
    $writer->save($this->tempFilename);
  }

  /**
   * Set the response indicating the spreadsheet was generated and stream it.
   */
  private function setSuccessResponse() {
    $this->setOutputFileName();
    $output_reponse = file_get_contents($this->tempFilename);
    $response = new Response($output_reponse);
    $response->headers->set('Content-Type', 'Content-type:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', "attachment; filename=\"{$this->outputFilename}\"");
    $this->response = $response;
  }

  /**
   * Set the filename to be used when serving the file to the user.
   */
  private function setOutputFileName() {
    $this->outputFilename = sprintf(
      "unblib_%s_holdings_%s.xlsx",
      strtolower($this->type->getName()),
      time()
    );
  }

}
