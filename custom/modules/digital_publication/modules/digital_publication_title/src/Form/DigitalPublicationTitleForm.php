<?php

namespace Drupal\digital_publication_title\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Digital publication title edit forms.
 *
 * @ingroup digital_publication_title
 */
class DigitalPublicationTitleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\digital_publication_title\Entity\DigitalPublicationTitle */
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
        drupal_set_message($this->t('Created the %label Digital publication title.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Digital publication title.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.digital_publication_title.canonical', ['digital_publication_title' => $entity->id()]);
  }

}
