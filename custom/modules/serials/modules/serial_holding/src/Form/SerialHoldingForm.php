<?php

namespace Drupal\serial_holding\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\serial_holding\TaxonomyHelper;
use Drupal\taxonomy\Entity\Term;

/**
 * Form controller for Serial holding edit forms.
 *
 * @ingroup serial_holding
 */
class SerialHoldingForm extends ContentEntityForm {
  const DIGITAL_HOLDING_INST_ID = '654';

  /**
   * The parent EID.
   *
   * @var int
   */
  protected $parentEid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    /* @var $entity \Drupal\serial_holding\Entity\SerialHolding */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;
    $this->parentEid = $node;

    // This has been called from the edit form link.
    if (empty($this->parentEid)) {
      $this->parentEid = $entity->getParentTitle()->id();
    }

    // Institution|taxonomy term|Name reserved - Digital Holdings (NBNP-318).
    $digital_inst_label = Term::load(self::DIGITAL_HOLDING_INST_ID)
      ->get('name')
      ->value;
    $is_digital_holding = FALSE;
    $digital_key = array_search("Digital", $form['holding_type']['widget']['#options']);
    $digital_inst_key = array_search($digital_inst_label, $form['holding_institution']['widget']['#options'], TRUE);
    if (
      !empty($form['holding_type']['widget']['#default_value'][0]) &&
      $form['holding_type']['widget']['#default_value'][0] == $digital_key
    ) {
      $is_digital_holding = TRUE;
    }
    if (!$is_digital_holding) {
      // Hide digital-related select options if holding type isn't digital.
      unset($form['holding_type']['widget']['#options'][$digital_key]);
      unset($form['holding_institution']['widget']['#options'][$digital_inst_key]);
    }
    else {
      // Restrict select option if holding type=digital.
      $form['holding_type']['widget']['#options'] = array_intersect(
        [$digital_key => 'Digital'],
        $form['holding_type']['widget']['#options']
      );
      $form['holding_institution']['widget']['#options'] = array_intersect(
        [$digital_inst_key => $digital_inst_label],
        $form['holding_institution']['widget']['#options']
      );
    }

    if ($is_digital_holding) {
      // Disable start/end date widgets.
      $description = $this->t('Manually setting date ranges is not possible for digital holdings.');
      $form['holding_start_date']['widget'][0]['value']['#description'] = $description;
      $form['holding_start_date']['#disabled'] = 'disabled';
      $form['holding_end_date']['widget'][0]['value']['#description'] = $description;
      $form['holding_end_date']['#disabled'] = 'disabled';

      // Hide the embargo fields.
      hide($form['holding_start_date_embargo']);
      hide($form['holding_end_date_embargo']);

      // Also hide the now redundant Holding Coverage Statement field.
      hide($form['holding_coverage']);

    }

    // Get term ids for the holding types.
    $physical_id = TaxonomyHelper::getHoldingTermId('Print');
    $microfilm_id = TaxonomyHelper::getHoldingTermId('Microform');
    $online_id = TaxonomyHelper::getHoldingTermId('Online');
    $digital_id = TaxonomyHelper::getHoldingTermId('Digital');

    $form['holding_microform_type']['#states'] = [
      'visible-slide' => [
        'select[name="holding_type"]' => [
          ['value' => $microfilm_id],
        ],
      ],
      'required' => [
        'select[name="holding_type"]' => [
          ['value' => $microfilm_id],
        ],
      ],
    ];

    // If we have term types 'Print' or 'Microfilm', set up states.
    if ($physical_id != 0 || $microfilm_id != 0) {
      $form['holding_location']['#states'] = [
        'visible-slide' => [
          'select[name="holding_type"]' => [
            ['value' => $physical_id],
            ['value' => $microfilm_id],
          ],
        ],
      ];
      $form['holding_call_no']['#states'] = [
        'visible-slide' => [
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

    // If we have term types 'Print', set up states.
    if ($physical_id != 0) {
      $form['holding_retention']['#states'] = [
        'visible-slide' => [
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
        'visible-slide' => [
          'select[name="holding_type"]' => [
            ['value' => $microfilm_id],
          ],
        ],
      ];
      $form['holding_last_rec']['#states'] = [
        'visible-slide' => [
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

    // If we have term types 'Online', set up states.
    if ($online_id != 0) {
      $form['holding_uri']['#states'] = [
        'visible-slide' => [
          'select[name="holding_type"]' => [
            ['value' => $online_id],
          ],
        ],
        'required' => [
          'select[name="holding_type"]' => [
            ['value' => $online_id],
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Do not validate cancelled holding forms.
    $user_input = $form_state->getUserInput();
    if ($user_input['op'] == "Cancel") {
      return FALSE;
    }

    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();
    $holding_start_date = $values['holding_start_date'][0]['value'];
    $holding_end_date = $values['holding_end_date'][0]['value'];

    if (
      !empty($holding_start_date) &&
      !empty($holding_end_date) &&
      $holding_start_date > $holding_end_date
    ) {
      $form_state->setErrorByName(
        'holding_start_date',
        $this->t('The holding start date cannot be later than the end date.')
      );
    }

    // Disallow empty URI field for Online holding type.
    $holding_target_id = $form_state->getValue("holding_type")[0]["target_id"];
    $holding_uri = $form_state->getValue("holding_uri")[0]["uri"];
    if ($holding_target_id == 3 && empty($holding_uri)) {
      $form_state->setErrorByName(
        'holding_uri',
        $this->t("The 'URI' field cannot be empty for 'Online' holding type.")
      );
    }

    // At least 1 Microform Type option required for Microform holding type.
    $microform_type = $form_state->getValue("holding_microform_type")[0]["value"];
    if ($holding_target_id == 2 && empty($microform_type)) {
      $form_state->setErrorByName(
        'holding_microform_type',
        $this->t("A 'Microform Type' must be selected for holdings of type 'Microform'.")
      );
    }
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

    return parent::submitForm($form, $form_state);
  }

}
