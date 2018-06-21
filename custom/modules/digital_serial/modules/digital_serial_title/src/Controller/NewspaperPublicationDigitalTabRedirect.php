<?php

namespace Drupal\digital_serial_title\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * NNewspaperPublicationDigitalTabRedirect object.
 */
class NewspaperPublicationDigitalTabRedirect extends ControllerBase {

  /**
   * Determine the proper action for the publication digital issue tab.
   *
   * @param int $node
   *   The publication node to triage against.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array if no title exists, a redirect response otherwise.
   */
  public function triageRequest($node = NULL) {
    $nid = $node;
    $node = Node::load($nid);

    $query = \Drupal::entityQuery('digital_serial_title')
      ->condition('status', 1)
      ->condition('parent_title', $node->id());

    if (!empty($query->execute())) {
      return $this->redirect('entity.digital_serial_title.canonical', ['digital_serial_title' => 1]);
    }
    return $this->formBuilder()->getForm('Drupal\digital_serial_title\Form\PublicationDigitalTitleAddForm', $node->id());
  }

}
