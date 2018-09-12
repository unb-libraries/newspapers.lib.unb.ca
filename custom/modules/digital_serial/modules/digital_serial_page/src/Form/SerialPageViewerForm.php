<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\digital_serial_issue\Entity\SerialIssueInterface;
use Drupal\digital_serial_page\Entity\SerialPageInterface;
use Drupal\digital_serial_page\SerialPageHocr;
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
    $referrer = \Drupal::request()->server->get('HTTP_REFERER');

    if ((strpos($referrer, 'search') !== FALSE)) {
      $link_text = "Back to search results";
      $url = $referrer;
    }
    else {
      $link_text = "Back to " . $digital_serial_issue->getDisplayTitle();
      $url = "internal:/serials/{$digital_serial_title->id()}/issues/{$digital_serial_issue->id()}";
    }
    $form['page_view']['back_link'] = [
      '#markup' => Link::fromTextAndUrl(
        $this->t(
          '@link_label',
          [
            '@link_label' => $link_text,
          ]
        ),
        Url::fromUri($url)
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

    $file = $digital_serial_page->get('page_image')->entity;
    $uri = $file->getFileUri();
    $image_path = file_url_transform_relative(file_create_url($uri));

    $overlays = [];
    $highlight = explode(' ', \Drupal::request()->query->get('highlight'));
    $hocr = $digital_serial_page->getPageHocr();
    if (!empty($hocr)) {
      $hocr_obj = new SerialPageHocr($hocr);
      $results = $hocr_obj->search($highlight, ['case_sensitive' => TRUE]);
      $page = $hocr_obj->getPageDimensions();

      foreach ($results as $ocr_item) {
        $bounding_box = $ocr_item['bbox'];
        $overlays[] = [
          'x' => $bounding_box['left'] / $page['width'],
          'y' => $bounding_box['top'] / $page['width'],
          'width' => ($bounding_box['right'] - $bounding_box['left']) / $page['width'],
          'height' => ($bounding_box['bottom'] - $bounding_box['top']) / $page['width'],
          'className' => "digital-serial-page-highlight",
        ];
      }
    }

    $form['#attached'] = [
      'library' => [
        'digital_serial_page/openseadragon',
        'digital_serial_page/openseadragon_viewer',
      ],
      'drupalSettings' => [
        'digital_serial_page' => [
          'jpg_filepath' => $image_path,
          'overlays' => $overlays,
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
