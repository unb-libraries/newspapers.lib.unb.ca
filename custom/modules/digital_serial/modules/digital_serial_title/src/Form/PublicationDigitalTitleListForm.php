<?php

namespace Drupal\digital_serial_title\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * PublicationDigitalTitleListForm object.
 */
class PublicationDigitalTitleListForm extends FormBase {

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

    if (!$this->titleHasIssues($node)) {
      $form['issue_list']['no_issues'] = [
        '#markup' => t('No issues found'),
      ];
      return;
    }

  }

  /**
   * {@inheritdoc}
   */
  private function titleHasIssues(NodeInterface $node) {
    $query = \Drupal::entityQuery('digital_serial_title')
      ->condition('status', 1)
      ->condition('parent_title', $node->id());

    return !empty($query->execute());
  }

}
