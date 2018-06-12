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

    if ($digital_serial_issue == NULL) {
      // This has been called from Entity Operations field in table.
      $this->issueEid = $this->entity->getParentIssue()->id();
    }
    else {
      $this->issueEid = $digital_serial_issue;
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

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

    // Add module to entity reference.
    $issue = \Drupal::entityTypeManager()
      ->getStorage('digital_serial_issue')
      ->load($this->issueEid);

    $new_item = TRUE;
    foreach ($issue->getPageIds() as $page_id) {
      if ($page_id == $entity->id()) {
        $new_item = FALSE;
        break;
      }
    }
    if ($new_item) {
      $issue->addPage($entity);
    }
    $issue->save();

    // Redirect back to cabinet module list.
    $form_state->setRedirect(
      'digital_serial_issue.manage_pages',
      [
        'digital_serial_issue' => $this->issueEid,
      ]
    );
  }

}
