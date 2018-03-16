<?php

namespace Drupal\serial_holding\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting Serial holding entities.
 *
 * @ingroup serial_holding
 */
class SerialHoldingDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelURL() {
    $parent_project = $this->getEntity()->getParentEntity();
    return Url::fromUri("internal:/node/{$parent_project->id()}/holdings");
  }

}
