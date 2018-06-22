<?php

namespace Drupal\digital_serial_issue\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Form controller for Serial issue edit forms.
 *
 * @ingroup digital_serial_issue
 */
class SerialIssueForm extends ContentEntityForm {

  use MessengerTrait;

  /**
   * The parent entity of the digital issue.
   *
   * @var int
   */
  protected $parentEid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_title = NULL) {
    $form = parent::buildForm($form, $form_state);
    $this->parentEid = $digital_serial_title;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\digital_serial_issue\Entity\SerialIssue */
    $entity = &$this->entity;

    if (!empty($this->parentEid)) {
      $entity->setParentTitleById($this->parentEid);
    }

    $status = parent::save($form, $form_state);

    // Invalidate cache relating to parent.
    Cache::invalidateTags(["digital_serial_title:{$this->parentEid}"]);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage(
          $this->t(
            'Created the %label Serial issue.',
            [
              '%label' => $entity->label(),
            ]
          )
        );
        break;

      default:
        $this->messenger()->addMessage(
          $this->t(
            'Saved the %label Serial issue.',
            [
              '%label' => $entity->label(),
            ]
          )
        );
    }

    $form_state->setRedirect(
      'digital_serial_issue.title_issues',
      [
        'digital_serial_title' => $this->parentEid,
      ]
    );
  }

}
