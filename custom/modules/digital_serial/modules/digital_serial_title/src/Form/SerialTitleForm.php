<?php

namespace Drupal\digital_serial_title\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Digital Serial Title edit forms.
 *
 * @ingroup digital_serial_title
 */
class SerialTitleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\digital_serial_title\Entity\SerialTitle */
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
        drupal_set_message($this->t('Created the %label Digital Serial Title.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Digital Serial Title.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.digital_serial_title.canonical', ['digital_serial_title' => $entity->id()]);
  }

}
