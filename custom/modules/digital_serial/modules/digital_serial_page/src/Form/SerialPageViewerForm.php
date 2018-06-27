<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\digital_serial_issue\Entity\SerialIssueInterface;
use Drupal\digital_serial_page\Entity\SerialPageInterface;
use Drupal\digital_serial_title\Entity\SerialTitleInterface;

/**
 * ManageArchivalMasterForm object.
 */
class SerialPageViewerForm extends FormBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'digital_serial_page_page_viewer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SerialTitleInterface $digital_serial_title = NULL, SerialIssueInterface $digital_serial_issue = NULL, SerialPageInterface $digital_serial_page = NULL) {
    $form = [];

    $form['page_view']['back_link'] = [
      '#markup' => Link::fromTextAndUrl(
        $this->t(
          '< Back to @issue_label',
          [
            '@issue_label' => $digital_serial_issue->getDisplayTitle(),
          ]
        ),
        Url::fromUri("internal:/serials/{$digital_serial_title->id()}/issues/{$digital_serial_issue->id()}")
      )->toString(),
    ];

    $title = [
      '#type' => 'processed_text',
      '#text' => $this->t('High Resolution Image'),
      '#format' => 'full_html',
      '#langcode' => 'en',
    ];
    $form['page_view']['title'] = $title;
    $form['page_view']['title']['#prefix'] = '<h2 class="viewer-title">';
    $form['page_view']['title']['#suffix'] = '</h2>';

    $form['page_view']['zoom'] = [
      '#markup' => '<div id="seadragon-viewer"></div>',
    ];

    $form['#attached'] = [
      'library' => [
        'digital_serial_page/openseadragon',
        'digital_serial_page/openseadragon_viewer',
      ],
      'drupalSettings' => [
        'digital_serial_page' => [
          'dzi_filepath' => "/sites/default/files/dzi/1.dzi",
        ],
      ],
    ];

    $file = $digital_serial_page->get('page_ocr')->entity;
    if (!empty($file)) {
      $title = [
        '#type' => 'processed_text',
        '#text' => $this->t('Text On Page'),
        '#format' => 'full_html',
        '#langcode' => 'en',
      ];
      $form['page_text']['title'] = $title;
      $form['page_text']['title']['#prefix'] = '<h2 class="ocr-title">';
      $form['page_text']['title']['#suffix'] = '</h2>';

      $uri = $file->getFileUri();
      $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
      $file_path = $stream_wrapper_manager->realpath();
      $ocr_text = trim(file_get_contents($file_path));

      $form['page_text']['text'] = [
        '#markup' => "<pre>$ocr_text</pre>",
      ];
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

}
