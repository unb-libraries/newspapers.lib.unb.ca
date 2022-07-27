<?php

namespace Drupal\publication_holdings_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\node\Entity\Node;

/**
 * PublicationHoldingsBulkMigrationView object.
 */
class PublicationHoldingsBulkMigrationView extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'publication_holdings_bulk_migration_view';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $migration_id = NULL) {
    $form = [];

    $form['import_details'] = [
      '#type' => 'fieldset',
    ];
    $form['import_details']['header'] = [
      '#markup' => t(
        '<h2>Details for @import_id:</h2>',
        [
          '@import_id' => $migration_id,
        ]
      ),
    ];
    $form['import_details']['table'] = _publication_holdings_bulk_import_get_migration_table($migration_id);

    $migrate_targets = _publication_holdings_bulk_import_get_migration_destinations($migration_id);
    if (!empty($migrate_targets)) {
      // Construct header.
      $header = [
        t('Row'),
        t('Accession ID'),
        t('Scientific Name'),
        t('Collectors'),
        t('Date Collected'),
      ];
      // Build the rows.
      $rows = [];

      foreach ($migrate_targets as $target) {
        if (!empty($target->destid1)) {
          $node = Node::load($target->destid1);
          $collectors = _herbarium_specimen_get_collector_list($node);
          $rows[] = [
            'data' => [
              count($rows) + 1,
              $node->get('field_dwc_record_number')->value,
              Link::createFromRoute($node->getTitle(), 'entity.node.canonical', ['node' => $target->destid1]),
              render($collectors),
              $node->get('field_dwc_eventdate')->value,
            ],
          ];
        }
      }

      $form['import_details']['specimen_list'] = [
        '#type' => 'fieldset',
      ];

      $form['import_details']['specimen_list']['header'] = [
        '#markup' => t(
          '<h2><em>Specimens Imported:</em></h2>'
        ),
      ];

      $form['import_details']['specimen_list']['message_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      $form['import_details']['specimen_list']['pager'] = [
        '#type' => 'pager',
      ];

    }
    else {
      $form['import_details']['none_found'] = [
        '#markup' => t('Invalid Migration ID.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
