<?php

namespace Drupal\digital_serial_issue\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Serial issue entities.
 *
 * @ingroup digital_serial_issue
 */
class SerialIssueDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the issue view.
   */
  public function getCancelURL() {
    $entity = $this->getEntity();
    $title = $entity->getParentTitle();
    return Url::fromRoute(
      'digital_serial_issue.title_view_issue',
      [
        'digital_serial_title' => $title->id(),
        'digital_serial_issue' => $entity->id(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete Issue');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $title = $entity->getParentTitle();

    parent::submitForm($form, $form_state);

    // Redirect back to issue list.
    $form_state->setRedirect(
      'entity.digital_serial_title.canonical',
      [
        'digital_serial_title' => $title->id(),
      ]
    );
  }

}
