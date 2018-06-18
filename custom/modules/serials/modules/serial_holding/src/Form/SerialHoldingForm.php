<?php

namespace Drupal\serial_holding\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\serial_holding\TaxonomyHelper;

/**
 * Form controller for Serial holding edit forms.
 *
 * @ingroup serial_holding
 */
class SerialHoldingForm extends ContentEntityForm {

  protected $parentEid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    /* @var $entity \Drupal\serial_holding\Entity\SerialHolding */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    // Get term ids for the holding types.
    $physical_id = TaxonomyHelper::getHoldingTermId('Physical');
    $microfilm_id = TaxonomyHelper::getHoldingTermId('Microfilm');

    // If we have term types 'Physical' or 'Microfilm', set up states.
    if ($physical_id != 0 || $microfilm_id != 0) {
      $form['holding_location']['#states'] = [
        'visible' => [
          'select[name="holding_type"]' => [
            ['value' => $physical_id],
            ['value' => $microfilm_id],
          ],
        ],
      ];
      $form['holding_call_no']['#states'] = [
        'visible' => [
          'select[name="holding_type"]' => [
            ['value' => $physical_id],
            ['value' => $microfilm_id],
          ],
        ],
      ];
    }
    // Otherwise, hide them altogether.
    else {
      hide($form['holding_location']);
      hide($form['holding_call_no']);
    }

    // If we have term types 'Physical', set up states.
    if ($physical_id != 0) {
      $form['holding_retention']['#states'] = [
        'visible' => [
          'select[name="holding_type"]' => [
            ['value' => $physical_id],
          ],
        ],
      ];
    }
    // Otherwise, hide them altogether.
    else {
      hide($form['holding_retention']);
    }

    // If we have term types 'Microfilm', set up states.
    if ($microfilm_id != 0) {
      $form['holding_filed_as']['#states'] = [
        'visible' => [
          'select[name="holding_type"]' => [
            ['value' => $microfilm_id],
          ],
        ],
      ];
      $form['holding_last_rec']['#states'] = [
        'visible' => [
          'select[name="holding_type"]' => [
            ['value' => $microfilm_id],
          ],
        ],
      ];
    }
    // Otherwise, hide them altogether.
    else {
      hide($form['holding_filed_as']);
      hide($form['holding_last_rec']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Serial holding.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Serial holding.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.serial_holding.canonical', ['serial_holding' => $entity->id()]);
  }

}
