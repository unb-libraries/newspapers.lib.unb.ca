<?php

namespace Drupal\digital_serial_title\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\digital_serial_title\Entity\SerialTitle;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * PublicationDigitalTitleListForm object.
 */
class PublicationDigitalTitleListForm extends FormBase {

  /**
   * The parent entity of the digital title.
   *
   * @var int
   */
  protected $parentEid;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'digital_serial_title_publication_digital_title_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $form = [];
    $nid = $node;
    $node = Node::load($nid);
    $this->parentEid = $nid;

    $title = [
      "#type" => "processed_text",
      "#text" => t("Digital Issues"),
      "#format" => "full_html",
      "#langcode" => "en",
    ];

    $form['issue_list']['title'] = $title;
    $form['issue_list']['title']['#prefix'] = '<h2 class="issue-list-title">';
    $form['issue_list']['title']['#suffix'] = '</h2>';

    if (!$this->titleHasRegistered($node)) {
      $this->setLinkTitleElements($form, $form_state);
      return $form;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Form submit callback. Create a digital serial title for a publication.
   *
   * @param array $form
   *   The form to modify.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createDigitalTitle(array &$form, FormStateInterface $form_state) {
    // Create entity.
    $entity_values = [
      'user_id' => \Drupal::currentUser()->id(),
      'status' => 1,
      'parent_title' => $this->parentEid,
    ];

    $title = SerialTitle::create($entity_values);
    $title->save();

    // Redirect.
    $form_state->setRedirect('digital_serial_title.digital_issues',
      [
        'node' => $this->parentEid,
      ]
    );
  }

  /**
   * Add elements to a form that can create the digital serial title.
   *
   * @param array $form
   *   The form to modify.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function setLinkTitleElements(array &$form, FormStateInterface $form_state) {
    $form['issue_list']['no_issues'] = [
      '#markup' => t('This title has not been set up to archive digital issues. To do so, click "Create Digital Title" below.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $form['issue_list']['create'] = [
      '#type' => 'submit',
      '#value' => t('Create Digital Title'),
      '#submit' => [
        [$this, 'createDigitalTitle'],
      ],
    ];
  }

  /**
   * Determine if a publication has an associated digital serial title.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The publication node to check.
   *
   * @return bool
   *   TRUE if the publication has a serial title. FALSE otherwise.
   */
  private function titleHasRegistered(NodeInterface $node) {
    $query = \Drupal::entityQuery('digital_serial_title')
      ->condition('status', 1)
      ->condition('parent_title', $node->id());

    return !empty($query->execute());
  }

}
