<?php

namespace Drupal\digital_serial_issue\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Serial issue edit forms.
 *
 * @ingroup digital_serial_issue
 */
class SerialIssueForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\digital_serial_issue\Entity\SerialIssue */
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
        drupal_set_message($this->t('Created the %label Serial issue.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Serial issue.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.serial_issue.canonical', ['serial_issue' => $entity->id()]);
  }

}
