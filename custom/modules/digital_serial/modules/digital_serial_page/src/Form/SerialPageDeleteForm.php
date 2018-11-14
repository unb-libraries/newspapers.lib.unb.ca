<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Serial page entities.
 *
 * @ingroup digital_serial_page
 */
class SerialPageDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the issue list.
   */
  public function getCancelURL() {
    $entity = $this->getEntity();
    $issue = $entity->getParentIssue();
    $title = $issue->getParentTitle();

    return Url::fromRoute(
      'digital_serial_page.manage_pages',
      [
        'digital_serial_title' => $title->id(),
        'digital_serial_issue' => $issue->id(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete Page');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $issue = $entity->getParentIssue();
    $title = $issue->getParentTitle();

    parent::submitForm($form, $form_state);

    // Redirect back to page management.
    $form_state->setRedirect(
      'digital_serial_page.manage_pages',
      [
        'digital_serial_title' => $title->id(),
        'digital_serial_issue' => $issue->id(),
      ]
    );
  }

}
