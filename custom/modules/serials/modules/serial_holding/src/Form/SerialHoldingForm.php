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
    $this->parentEid = $node;

    $entity = $this->entity;

    // Get term ids for the holding types.
    $physical_id = TaxonomyHelper::getHoldingTermId('Physical');
    $microfilm_id = TaxonomyHelper::getHoldingTermId('Microfilm');
    $digital_id = TaxonomyHelper::getHoldingTermId('Digital');

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

    // If we have term types 'Digital', set up states.
    if ($digital_id != 0) {
      $form['holding_uri']['#states'] = [
        'visible' => [
          'select[name="holding_type"]' => [
            ['value' => $digital_id],
          ],
        ],
      ];
    }
    // Otherwise, hide them altogether.
    else {
      hide($form['holding_uri']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!empty($this->parentEid)) {
      $form_state->setValue('parent_title', $this->parentEid);
    }

    $form_state->setRedirect('serial_holding.manage_serial_holdings',
      [
        'node' => $this->parentEid,
      ]
    );

    return parent::submitForm($form, $form_state);;
  }

}
