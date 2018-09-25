<?php

namespace Drupal\digital_serial_title\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\digital_serial_title\Entity\SerialTitle;

/**
 * Form controller for Digital Serial Title edit forms.
 *
 * @ingroup digital_serial_title
 */
class SerialTitleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\digital_serial_title\Entity\SerialTitle */
    $entity = &$this->entity;
    $this->saveAndReportSaveAction($form, $form_state, $entity);

    $form_state->setRedirect(
      'entity.digital_serial_title.canonical',
      [
        'digital_serial_title' => $entity->id(),
      ]
    );
  }

  /**
   * Report the entity save action to the user.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $status
   *   The status of the entity save operation.
   * @param \Drupal\digital_serial_title\Entity\SerialTitle $entity
   *   The entity that was saved.
   */
  private function reportSaveAction(array $form, FormStateInterface $form_state, $status, SerialTitle $entity) {
    switch ($status) {
      case SAVED_NEW:
        $op = 'Created';
        break;

      default:
        $op = 'Saved';
    }

    $this->messenger()->addMessage(
      $this->t(
        '%op the %label Serial Title.',
        [
          '%op' => $op,
          '%label' => $entity->label(),
        ]
      )
    );
  }

  /**
   * Save the entity and report the action to the user.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\digital_serial_title\Entity\SerialTitle $entity
   *   The entity that was saved.
   */
  private function saveAndReportSaveAction(array $form, FormStateInterface $form_state, SerialTitle $entity) {
    $status = parent::save($form, $form_state);
    $this->reportSaveAction($form, $form_state, $status, $entity);
  }

}
