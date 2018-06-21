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

    $this->setListElements($form, $form_state, $node);

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
   * {@inheritdoc}
   */
  private function setListElements(array &$form, FormStateInterface $form_state, NodeInterface $node) {
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

      return;
    }

  }

  /**
   * {@inheritdoc}
   */
  private function titleHasRegistered(NodeInterface $node) {
    $query = \Drupal::entityQuery('digital_serial_title')
      ->condition('status', 1)
      ->condition('parent_title', $node->id());

    return !empty($query->execute());
  }

  /**
   * {@inheritdoc}
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

}
