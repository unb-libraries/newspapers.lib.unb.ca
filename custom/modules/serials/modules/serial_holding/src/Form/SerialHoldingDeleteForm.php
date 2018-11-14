<?php

namespace Drupal\serial_holding\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Serial holding entities.
 *
 * @ingroup serial_holding
 */
class SerialHoldingDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $parent_project = $this->getEntity()->getParentTitle();

    return Url::fromRoute(
      'serial_holding.manage_serial_holdings',
      [
        'node' => $parent_project->id(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete Holding');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $parent_project = $this->getEntity()->getParentTitle();
    parent::submitForm($form, $form_state);

    // Redirect back to holding management.
    $form_state->setRedirect(
      'serial_holding.manage_serial_holdings',
      [
        'node' => $parent_project->id(),
      ]
    );
  }

}
