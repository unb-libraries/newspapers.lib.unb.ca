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
  protected $titleEid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_title = NULL, $digital_serial_issue = NULL) {
    /* @var $entity \Drupal\digital_serial_page\Entity\SerialPage */

    if ($digital_serial_issue == NULL) {
      // This has been called from Entity Operations field in table.
      $this->issueEid = $this->entity->getParentIssue()->id();
    }
    else {
      $this->issueEid = $digital_serial_issue;
    }

    if ($digital_serial_title == NULL) {
      // This has been called from Entity Operations field in table.
      $this->titleEid = $this->entity->getParentIssue()->getParentTitle()->id();
    }
    else {
      $this->titleEid = $digital_serial_title;
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\digital_serial_page\Entity\SerialPage */
    $entity = &$this->entity;

    $entity->setParentIssueById($this->issueEid);

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

    // Redirect back to cabinet module list.
    $form_state->setRedirect(
      'digital_serial_page.manage_pages',
      [
        'digital_serial_title' => $this->titleEid,
        'digital_serial_issue' => $this->issueEid,
      ]
    );
  }

}
