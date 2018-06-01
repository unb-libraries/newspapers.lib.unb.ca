<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Serial page edit forms.
 *
 * @ingroup digital_serial_page
 */
class SerialPageForm extends ContentEntityForm {

  protected $issueEid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_issue = NULL) {
    /* @var $entity \Drupal\digital_serial_page\Entity\SerialPage */
    $this->issueEid = $digital_serial_issue;

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
        drupal_set_message($this->t('Created the %label Serial page.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Serial page.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.digital_serial_page.canonical', ['digital_serial_page' => $entity->id()]);
  }

}
