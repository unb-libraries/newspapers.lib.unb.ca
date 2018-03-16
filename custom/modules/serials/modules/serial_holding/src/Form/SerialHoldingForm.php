<?php

namespace Drupal\serial_holding\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

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
