<?php

namespace Drupal\publication_holdings_bulk_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
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
        t('ID'),
        t('Parent Publication'),
        t('Type'),
        t('Start'),
        t('End'),
        t('Institution'),
        t('Holding'),
      ];
      // Build the rows.
      $rows = [];

      foreach ($migrate_targets as $target) {
        if (!empty($target->destid1)) {
          $holding = \Drupal::entityTypeManager()->getStorage('serial_holding')->load($target->destid1);
          $publication = $holding->getParentTitle();
          $type = !empty($holding->getHoldingType()) ? $holding->getHoldingType()->label() : 'Unknown';
          if ($type == 'Microform') {
            $microform_type = !empty($holding->getMicroformType()) ? $holding->getMicroformType() : 'Unknown';
            $type = "$type ($microform_type)";
          }

          $rows[] = [
            'data' => [
              count($rows) + 1,
              Link::createFromRoute($publication->label(), 'entity.node.canonical', ['node' => $publication->id()]),
              $type,
              !empty($holding->getHoldingStartDate()) ? $holding->getHoldingStartDate()->format('Y-m-d') : 'Unknown',
              !empty($holding->getHoldingEndDate()) ? $holding->getHoldingEndDate()->format('Y-m-d') : 'Unknown',
              !empty($holding->getInstitution()) ? $holding->getInstitution()->label() : 'Unknown',
              Link::fromTextAndUrl(
                t('Link'),
                Url::fromUri("internal:/admin/structure/serial_holding/$target->destid1/edit")
              )->toString()
            ],
          ];
        }
      }

      $form['import_details']['holding_list'] = [
        '#type' => 'fieldset',
      ];

      $form['import_details']['holding_list']['header'] = [
        '#markup' => t(
          '<h2><em>Holdings Imported:</em></h2>'
        ),
      ];

      $form['import_details']['holding_list']['message_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      $form['import_details']['holding_list']['pager'] = [
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
