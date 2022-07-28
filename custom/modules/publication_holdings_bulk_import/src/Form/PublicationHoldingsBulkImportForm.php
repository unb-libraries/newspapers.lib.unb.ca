<?php

namespace Drupal\publication_holdings_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\publication_holdings_bulk_import\PublicationHoldingsCsvMigration;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * PublicationHoldingsBulkImportForm object.
 */
class PublicationHoldingsBulkImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'publication_holdings_bulk_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['upload_import'] = [
      '#type' => 'fieldset',
    ];
    $form['upload_import']['header'] = [
      '#markup' => t(
        '<h2>Upload Publication Holdings</h2>'
      ),
    ];
    $form['upload_import']['message'] = [
      '#markup' => t(
        '<p style="margin:10px;">This interface provides a method to create multiple publication holdings by uploading a CSV file in the specified format. For an empty CSV file matching the required format, please click the \'Download an empty CSV template for this format\' link below. <b>Please note that, in the CSV file, the column titles must remain unchanged.</b></p>'
      ),
    ];

    $import_formats = _publication_holdings_bulk_import_get_import_formats();
    $select_options = [];
    foreach ($import_formats as $import_format) {
      $select_options[$import_format['id']] = $import_format['description'];
    }
    $default_format = array_shift($import_formats);
    $form['upload_import']['import_format'] = [
      '#type' => 'select',
      '#title' => t('Import Format:'),
      '#required' => TRUE,
      '#options' => $select_options,
      '#default_value' => $default_format['id'],
      '#ajax' => [
        'callback' => [$this, 'rebuildDownloadTemplateLink'],
        'event' => 'change',
        'wrapper' => 'download-template',
      ],
    ];

    $form['upload_import']['download_template'] = [
      '#markup' => $this->generateTemplateDownloadLink($default_format['id']),
      '#prefix' => '<div id="download-template">',
      '#suffix' => '</div>',
    ];

    $form['upload_import']['import_file'] = [
      '#title' => t('Import File'),
      '#type' => 'managed_file',
      '#required' => TRUE,
      '#description' => t('Upload a file, allowed extensions: CSV'),
      '#upload_location' => 'public://holding_csv_upload/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['upload_import']['submit'] = [
      '#type' => 'submit',
      '#prefix' => '<br>',
      '#value' => t('Import Holdings'),
    ];

    $form['import_history'] = [
      '#type' => 'fieldset',
    ];

    $form['import_history']['header'] = [
      '#markup' => t(
        '<h2><em>Import History</em></h2>'
      ),
    ];

    $form['import_history']['message'] = [
      '#markup' => t(
        '<p style="margin:10px;">Below, you will find a listing of previous bulk imports performed since the last server restart. After the next server restart, references to them will be removed from this display.</p>'
      ),
    ];

    $form['import_history']['table'] = _publication_holdings_bulk_import_get_migration_table();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();

    if (empty($user_input['_triggering_element_value']) || $user_input['_triggering_element_value'] != 'Remove') {
      ini_set("auto_detect_line_endings", '1');
      if (!empty($form_state->getValue('import_file')[0])) {
        $file = File::Load($form_state->getValue('import_file')[0]);
        if (!empty($file)) {
          $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
          $format_id = $form_state->getValue('import_format');

          if (
            $this->validateImportFormat($form_state, $format_id) &&
            $this->validateCsvStructure($form, $form_state, $file_path,
              $format_id) &&
            $this->validateRowData($form, $form_state, $file_path, $format_id) &&
            $this->validateData($form, $form_state, $file_path, $format_id)

          ) {
            // No errors found. Do nothing, process all validations sequentially.
          };
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue('import_file')[0];
    $file = File::Load($fid);
    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $file->setPermanent();
    $file->save();

    $import_id = $form_state->getValue('import_format');

    $migrateObject = new PublicationHoldingsCsvMigration(
      $import_id,
      $file_path
    );
    drupal_flush_all_caches();

    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migrateObject->importId);
    $map = $migration->getIdMap();
    $source_plugin = $migration->getSourcePlugin();
    $source_rows = $source_plugin->count();
    $unprocessed = $source_rows - $map->processedCount();

    $batch = [
      'title' => t('Importing Publication Holdings'),
      'init_message' => t('Importing Publication Holdings'),
      'operations' => [],
    ];

    $batch_items_created = 0;
    while ($batch_items_created < $unprocessed) {
      $batch['operations'][] = [
        [
          'Drupal\publication_holdings_bulk_import\PublicationHoldingsCsvMigration',
          'runCsvMigrationBatch',
        ],
        [
          $migrateObject->importId,
          1,
        ],
      ];
      $batch_items_created++;
    }
    batch_set($batch);
  }

  /**
   * Validate a CSV file's general structure.
   *
   * @param array $form
   *   The form API array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $file_path
   *   The path the the CSV file.
   * @param string $format_id
   *   The name of the migration id to leverage.
   *
   * @return bool
   *   TRUE if the file validates.
   */
  private function validateCsvStructure(array &$form, FormStateInterface $form_state, $file_path, $format_id) {
    // Validate CSV structure.
    try {
      $reader = Reader::createFromPath($file_path, 'r');
      $nbColumns = $reader->fetchOne();

      // Check header matches expected number of rows from format.
      $import_format = _publication_holdings_bulk_import_get_import_format($format_id);
      if (count($nbColumns) != count($import_format['columns'])) {
        $form_state->setErrorByName('import_file', $this->t(
          'The number of columns in the file (@file_columns) is different than expected by the import format (@format_columns).',
          [
            '@format_columns' => count($import_format['columns']),
            '@file_columns' => count($nbColumns),
          ]
        ));
        return FALSE;
      }

      // Check Row Consistency.
      foreach ($reader as $row_num => $column) {
        if (count($column) != count($nbColumns)) {
          $form_state->setErrorByName('import_file', $this->t(
            'The number of columns in row #@row_num (@row_columns) of the file is not the same as the header (@header_columns)',
            [
              '@row_num' => $row_num,
              '@row_columns' => count($column),
              '@header_columns' => count($nbColumns),
            ]
          ));
          return FALSE;
        }
      }
    }
    catch (Exception $e) {
      $form_state->setErrorByName('import_file', $this->t('Selected file is not in valid CSV format.'));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validate a CSV import's row data to determine if any errors may exist.
   *
   * @param array $form
   *   The form API array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $file_path
   *   The path the the CSV file.
   * @param string $format_id
   *   The name of the migration id to leverage.
   *
   * @return bool
   *   TRUE if the row validates.
   */
  private function validateRowData(array &$form, FormStateInterface $form_state, $file_path, $format_id) {
    $errors = FALSE;
    $reader = Reader::createFromPath($file_path, 'r');
    $stmt = (new Statement())->offset(1);
    $dataRows = $stmt->process($reader);
    $import_format = _publication_holdings_bulk_import_get_import_format($format_id);

    // Iterate and validate data.
    foreach ($dataRows as $row_id => $row) {
      $data_row_id = $row_id + 2;
      foreach ($row as $column_id => $column_data) {
        if (!empty($column_data)) {
          if (!empty($import_format['columns'][$column_id]['validate'])) {
            foreach ($import_format['columns'][$column_id]['validate'] as $validator) {
              // Pack data onto arguments.
              $function_args = ['data' => $column_data] + $validator['args'];
              if (!$validator['function'](...array_values($function_args))) {
                // Validation failed.
                $errors = TRUE;
                $this->messenger()->addError("{$import_format['columns'][$column_id]['name']} validation failed in row $data_row_id, column $column_id : $column_data {$validator['error']}.");
              }
            }
          }
        }
        elseif (!isset($import_format['columns'][$column_id]['required']) || $import_format['columns'][$column_id]['required'] === TRUE) {
          $errors = TRUE;
          $this->messenger()->addError("{$import_format['columns'][$column_id]['name']} validation failed in row $data_row_id : required value.");
        }
      }
    }

    if ($errors) {
      $form_state->setErrorByName(
        'import_file',
        t('One or more errors were found while validating the import file data. Please correct them and resubmit.')
      );
    }

    return empty($errors);
  }

  /**
   * Validate a CSV import's row entire data to determine if errors may exist.
   *
   * @param array $form
   *   The form API array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $file_path
   *   The path the the CSV file.
   * @param string $format_id
   *   The name of the migration id to leverage.
   */
  private function validateData(array &$form, FormStateInterface $form_state, $file_path, $format_id) {
    $errors = FALSE;
    $reader = Reader::createFromPath($file_path, 'r');
    $stmt = (new Statement())->offset(1);
    $dataRows = $stmt->process($reader);
    $import_format = _publication_holdings_bulk_import_get_import_format($format_id);

    // Iterate and validate data.
    foreach ($dataRows as $row_id => $row) {
      if (!empty($import_format['validate'])) {
        foreach ($import_format['validate'] as $validator_id => $validator) {
          // Pack key column args with values.
          $validator_args = [];
          foreach ($validator['column_args'] as $column_key) {
            $validator_args[] = $row[$column_key];
          }

          // Validate.
          if (!$validator['function'](...array_values($validator_args))) {
            $data_row_id = $row_id + 2;
            // Validation failed.
            $errors = TRUE;
            $this->messenger()->addError("{$validator['name']} validation failed in row $data_row_id, {$validator['error']}.");
          }
        }
      }
    }
    if ($errors) {
      $form_state->setErrorByName(
        'import_file',
        t('One or more errors were found while validating the import file data. Please correct them and resubmit.')
      );
    }
  }

  /**
   * Validate a CSV import format value.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $format_id
   *   The name of the migration id to leverage.
   *
   * @return bool
   *   TRUE if $format_id is a valid import format.
   */
  private function validateImportFormat(FormStateInterface $form_state, $format_id) {
    if (empty(_publication_holdings_bulk_import_get_import_format($format_id))) {
      $form_state->setErrorByName('import_file', $this->t('The import format is invalid.'));
      return FALSE;
    };
    return TRUE;
  }

  /**
   * Update the CSV template download link.
   *
   * @param array $form
   *   The form API being rendered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The new form API element.
   */
  public function rebuildDownloadTemplateLink(array &$form, FormStateInterface $form_state) {
    $format_id = $form_state->getValue('import_format');
    $import_format = _publication_holdings_bulk_import_get_import_format($format_id);

    if (empty($import_format)) {
      $form['upload_import']['download_template']['#markup'] = t('No template found for this import format.');
    }
    else {
      $form['upload_import']['download_template']['#markup'] = $this->generateTemplateDownloadLink($format_id);
    }

    return $form['upload_import']['download_template'];
  }

  /**
   * Generate a download link for the import format template.
   *
   * @param string $format_id
   *   The name of the migration id to leverage.
   *
   * @return string
   *   The markup value for the link.
   */
  public static function generateTemplateDownloadLink($format_id) {
    return Link::fromTextAndUrl(
      t('Download an empty CSV template for this format'),
      Url::fromUri("internal:/bulk_import_holdings/format/$format_id")
    )->toString();
  }

}
