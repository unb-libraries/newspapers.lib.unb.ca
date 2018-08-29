<?php

namespace Drupal\digital_serial_issue\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Views;

/**
 * DigitalSerialIssueListForm object.
 */
class DigitalSerialIssueListForm extends FormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_title = NULL) {
    $form = [];
    $eid = $digital_serial_title;
    $this->parentEid = $eid;

    $title = [
      "#type" => "processed_text",
      "#text" => t("Issues"),
      "#format" => "full_html",
      "#langcode" => "en",
    ];

    $form['issue_list']['title'] = $title;
    $form['issue_list']['title']['#prefix'] = '<h2 class="issue-list-title">';
    $form['issue_list']['title']['#suffix'] = '</h2>';

    $view = Views::getView('digital_issue_list');
    $view->setDisplay('block_2');
    $view->setArguments([$this->parentEid]);
    $render = $view->render();
    $form['select_block_view'] = $render;

    $form['add_issue'] = [
      '#type' => 'link',
      '#title' => t('Add New Issue'),
      '#url' => Url::fromRoute(
        'digital_serial_issue.title_add_issue',
        [
          'digital_serial_title' => $this->parentEid,
        ]
      ),
      '#attributes' => [
        'class' => ['btn btn-success'],
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

}
