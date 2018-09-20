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

    $prev_next = $this->getPrevNextPageUrls(
      $digital_serial_title->id(),
      $digital_serial_issue->id(),
      $digital_serial_page->id()
    );

    $prev_text = '« ' . t('Previous');
    $next_text = t('Next') . ' »';

    if ((strpos($referrer, 'search') !== FALSE)) {
      $back_text = "Back to search results";
      $url = Url::fromUri($referrer);
    }
    else {
      $back_text = "Back to " . $digital_serial_issue->getDisplayTitle();
      $uri = "internal:/serials/{$digital_serial_title->id()}/issues/{$digital_serial_issue->id()}";
      $url = Url::fromUri($uri);
    }

    $link_options = [
      'attributes' => [
        'class' => [
          'back-link',
        ],
      ],
    ];
    $url->setOptions($link_options);

    $form['page_view']['back_link'] = [
      '#markup' => Link::fromTextAndUrl(
        $this->t(
          '@link_label',
          [
            '@link_label' => $back_text,
          ]
        ),
        $url)
        ->toString(),
    ];
    $form['page_view']['back_link'];

    $form['page_view']['nav'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pager'],
      ],
    ];

    $disabled_link_options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-default',
          'disabled',
        ],
      ],
    ];
    $link_options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-primary',
        ],
      ],
    ];
    if (empty($prev_next['previous'])) {
      $prev_next['previous'] = Url::fromUri('internal:#');
      $prev_next['previous']->setOptions($disabled_link_options);
    }
    else {
      $prev_next['previous']->setOptions($link_options);
    }
    $prev_link = [
      '#markup' => Link::fromTextAndUrl($prev_text, $prev_next['previous'])
        ->toString(),
    ];
    $form['page_view']['nav']['prev_page'] = $prev_link;

    if (empty($prev_next['next'])) {
      $prev_next['next'] = Url::fromUri('internal:#');
      $prev_next['next']->setOptions($disabled_link_options);
    }
    else {
      $prev_next['next']->setOptions($link_options);
    }
    $next_link = [
      '#markup' => Link::fromTextAndUrl($next_text, $prev_next['next'])
        ->toString(),
    ];
    $form['page_view']['nav']['next_page'] = $next_link;

    $form['page_view']['zoom'] = [
      '#markup' => '<div id="seadragon-viewer"></div>',
    ];

    $file = $digital_serial_page->get('page_image')->entity;
    $uri = $file->getFileUri();
    $image_path = file_url_transform_relative(file_create_url($uri));

    $overlays = [];
    $highlight = explode(' ', \Drupal::request()->query->get('highlight'));

    if (!empty($highlight[0])) {
      $hocr = $digital_serial_page->getPageHocr();
      if (!empty($hocr)) {
        $hocr_obj = new SerialPageHocr($hocr);
        $results = $hocr_obj->search($highlight, ['case_sensitive' => FALSE]);
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

    return $form;
  }

  /**
   * Get a url corresponding to a page.
   *
   * @param int $title_id
   *   The serial title ID.
   * @param int $issue_id
   *   The serial issue ID.
   * @param int $page_id
   *   The serial page ID.
   *
   * @return \Drupal\Core\Url
   *   The Drupal URL.
   */
  private static function getPageUrl($title_id, $issue_id, $page_id) {
    $uri = "internal:/serials/$title_id/issues/$issue_id/pages/$page_id";
    return Url::fromUri($uri);
  }

  /**
   * Get the IDs of adjacent pages of a page in an issue.
   *
   * @param int $issue_id
   *   The serial issue ID.
   * @param int $page_id
   *   The serial page ID.
   *
   * @return array
   *   An associative array of previous and next page IDs.
   */
  private function getPrevNextPageIds($issue_id, $page_id) {
    $adjacent_page_ids = [
      'previous' => NULL,
      'next' => NULL,
    ];

    $query = \Drupal::entityQuery('digital_serial_page')
      ->condition('parent_issue', $issue_id)
      ->sort('page_sort');
    $entity_ids = $query->execute();

    if (!empty($entity_ids[$page_id - 1])) {
      $adjacent_page_ids['previous'] = $entity_ids[$page_id - 1];
    }
    if (!empty($entity_ids[$page_id + 1])) {
      $adjacent_page_ids['next'] = $entity_ids[$page_id + 1];
    }

    return $adjacent_page_ids;
  }

  /**
   * Get the Urls of adjacent pages of a page in an issue.
   *
   * @param int $title_id
   *   The serial title ID.
   * @param int $issue_id
   *   The serial issue ID.
   * @param int $page_id
   *   The serial page ID.
   *
   * @return array
   *   An associative array of previous and next page URLs.
   */
  private function getPrevNextPageUrls($title_id, $issue_id, $page_id) {
    $adjacent_page_urls = [
      'previous' => NULL,
      'next' => NULL,
    ];

    $prev_next_ids = $this->getPrevNextPageIds($issue_id, $page_id);
    if (!empty($prev_next_ids['previous'])) {
      $adjacent_page_urls['previous'] = $this->getPageUrl($title_id, $issue_id, $prev_next_ids['previous']);
    }
    if (!empty($prev_next_ids['next'])) {
      $adjacent_page_urls['next'] = $this->getPageUrl($title_id, $issue_id, $prev_next_ids['next']);
    }

    return $adjacent_page_urls;
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
