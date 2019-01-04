<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\digital_serial_page\Entity\SerialPage;

/**
 * Form controller for Serial page edit forms.
 *
 * @ingroup digital_serial_page
 */
class SerialPageForm extends ContentEntityForm {

  use MessengerTrait;

  /**
   * The entity ID of the parent digital issue.
   *
   * @var int
   */
  protected $issueEid;

  /**
   * The entity ID of the parent digital title.
   *
   * @var int
   */
  protected $titleEid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_title = NULL, $digital_serial_issue = NULL) {
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
    $this->saveAndReportSaveAction($form, $form_state, $entity);

    // Clear cache after save so page added in UI immediately shows in displays.
    drupal_flush_all_caches();

    // Redirect back to serial page list.
    $form_state->setRedirect(
      'digital_serial_page.manage_pages',
      [
        'digital_serial_title' => $this->titleEid,
        'digital_serial_issue' => $this->issueEid,
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
   * @param \Drupal\digital_serial_page\Entity\SerialPage $entity
   *   The entity that was saved.
   */
  private function reportSaveAction(array $form, FormStateInterface $form_state, $status, SerialPage $entity) {
    switch ($status) {
      case SAVED_NEW:
        $op = 'Created';
        break;

      default:
        $op = 'Saved';
    }

    $this->messenger()->addMessage(
      $this->t(
        '%op the %label Serial page.',
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
   * @param \Drupal\digital_serial_page\Entity\SerialPage $entity
   *   The entity that was saved.
   */
  private function saveAndReportSaveAction(array $form, FormStateInterface $form_state, SerialPage $entity) {
    $entity->setParentIssueById($this->issueEid);
    $status = parent::save($form, $form_state);
    $this->reportSaveAction($form, $form_state, $status, $entity);
  }

}
