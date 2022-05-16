<?php

namespace Drupal\digital_serial_title\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\digital_serial_title\Entity\SerialTitle;
use Drupal\node\Entity\Node;
use Drupal\serial_holding\Entity\SerialHolding;
use Drupal\serial_holding\TaxonomyHelper;

/**
 * PublicationDigitalTitleAddForm object.
 */
class PublicationDigitalTitleAddForm extends FormBase {

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
    return 'digital_serial_title_publication_digital_title_add_form';
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
      "#text" => $this->t("Digital Issues"),
      "#format" => "full_html",
      "#langcode" => "en",
    ];

    $form['issue_list']['title'] = $title;
    $form['issue_list']['title']['#prefix'] = '<h2 class="issue-list-title">';
    $form['issue_list']['title']['#suffix'] = '</h2>';

    $form['issue_list']['no_issues'] = [
      '#markup' => $this->t('This title has not been set up to archive digital issues. To do so, click "Create Digital Title" below.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $form['issue_list']['actions'] = [
      '#type' => 'actions',
    ];
    $form['issue_list']['actions']['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Digital Title'),
      '#submit' => [
        [$this, 'createDigitalTitle'],
      ],
    ];

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

    // Create a digital holding record.
    $digital_id = TaxonomyHelper::getHoldingTermId('Digital');
    $entity_values = [
      'holding_type' => $digital_id,
      'holding_coverage' => 'Access digitized content online',
      'user_id' => \Drupal::currentUser()->id(),
      'status' => 1,
      'parent_title' => $this->parentEid,
      'holding_digital_title' => $title->id(),
    ];
    $digital_holding = SerialHolding::create($entity_values);
    $digital_holding->save();

    // Redirect to the newly created title.
    $form_state->setRedirect('entity.digital_serial_title.canonical',
      [
        'digital_serial_title' => $title->id(),
      ]
    );
  }

}
