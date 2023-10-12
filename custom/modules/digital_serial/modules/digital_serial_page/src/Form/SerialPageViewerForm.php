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

    $prev_text = [
      '#type' => 'html_tag',
      '#tag'  => 'span',
      '#value' => $this->t('<span aria-hidden="true">« </span>Previous'),
      '#attributes' => [
        'aria-label' => ['Show previous page'],
      ],
    ];

    $current_page = $digital_serial_page->getActivePagerNo();
    $total_pages = $digital_serial_issue->getPageCount();
    $viewer_active_page_text = "Page $current_page of $total_pages";
    $viewer_active_pager_item = [
      '#type' => 'html_tag',
      '#tag'  => 'span',
      "#value" => $viewer_active_page_text,
      "#attributes" => [
        'class' => [
          'btn',
        ],
        'id' => [
          'active-viewer-page',
        ],
      ],
    ];

    $next_text = [
      '#type' => 'html_tag',
      '#tag'  => 'span',
      '#value' => $this->t('Next<span aria-hidden="true"> »</span>'),
      '#attributes' => [
        'aria-label' => ['Show next page'],
      ],
    ];

    if (strpos($referrer, 'search') !== FALSE) {
      $back_text = $this->t('Back to search results');
      $url = Url::fromUri($referrer);
    }
    else {
      $back_text = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'Digital issues for &ldquo;' . $digital_serial_issue->getIssueTitle() . '&rdquo;',
      ];
      $uri = "internal:/serials/browse/{$digital_serial_title->id()}";
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
        $back_text, $url)
        ->toString(),
    ];
    $form['page_view']['back_link'];

    $form['page_view']['pager'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'pagination',
        ],
        'aria-label' => 'Page viewer controls',
        'role' => 'group',
      ],
    ];

    $link_options = [
      'attributes' => [
        'class' => [
          'border',
          'btn',
          'btn-link',
          'pager-link',
        ],
      ],
    ];

    if (!empty($prev_next['previous'])) {
      $prev_next['previous']->setOptions($link_options);
      $prev_link = [
        '#markup' => Link::fromTextAndUrl($prev_text, $prev_next['previous'])
          ->toString(),
      ];
      $form['page_view']['pager']['prev_page'] = $prev_link;
    }

    $form['page_view']['pager']['active'] = $viewer_active_pager_item;

    if (!empty($prev_next['next'])) {
      $prev_next['next']->setOptions($link_options);
      $next_link = [
        '#markup' => Link::fromTextAndUrl($next_text, $prev_next['next'])
          ->toString(),
      ];
      $form['page_view']['pager']['next_page'] = $next_link;
    }

    $form['page_view']['zoom'] = [
      '#type' => 'container',
      '#id' => 'seadragon-viewer',
      '#attributes' => [
        'aria-label' => 'Zoomable Page',
        'role' => 'region',
      ],
    ];
    $file = $digital_serial_page->get('page_image')->entity;
    $uri = $file->getFileUri();
    $image_path = file_url_transform_relative(file_create_url($uri));
    $full_path = DRUPAL_ROOT . $image_path;
    $image_extension = pathinfo($full_path, PATHINFO_EXTENSION);
    $dzi_path = str_replace(".$image_extension", '.dzi', $full_path);

    // Determine if we're using DZI or the plain old image.
    if (file_exists($dzi_path)) {
      $tile_sources = str_replace(".$image_extension", '.dzi', $image_path);
    }
    else {
      $tile_sources = json_encode(
        [
          'type' => 'image',
          'url' => $image_path,
        ]
      );
    }

    // Highlighting.
    $overlays = [];
    $highlight = explode(' ', \Drupal::request()->query->get('highlight'));

    if (!empty($highlight[0])) {
      self::filterHighlightKeywords($highlight);
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
          'tile_sources' => $tile_sources,
          'overlays' => $overlays,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Filters out unwanted elements from the highlight keywords.
   *
   * @param array $keywords
   *   The keywords to filter.
   */
  private static function filterHighlightKeywords(array &$keywords): void {
    self::stripHighlightQuotes($keywords);
    $keywords = array_filter($keywords, [self, 'elementIsNotAStopWord']);
  }

  /**
   * Strips quotes from the highlight keywords.
   *
   * @param array $keywords
   *   The keywords to filter.
   */
  private static function stripHighlightQuotes(array &$keywords): void {
    $keywords = str_replace('"', '', $keywords);
  }

  /**
   * Determines if a string is not a stop word.
   *
   * @param string $element
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is not a stop word.
   */
  private static function elementIsNotAStopWord($element): bool {
    $stop_words = [
      'a',
      'an',
      'the',
      'and',
      'it',
      'for',
      'or',
      'but',
      'in',
      'my',
      'your',
      'our',
      'their',
    ];
    return !in_array(strtolower($element), $stop_words);
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

    $keys = array_flip(array_keys($entity_ids));
    $values = array_values($entity_ids);

    if (!empty($values[$keys[$page_id] - 1])) {
      $adjacent_page_ids['previous'] = $values[$keys[$page_id] - 1];
    }
    if (!empty($values[$keys[$page_id] + 1])) {
      $adjacent_page_ids['next'] = $values[$keys[$page_id] + 1];
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
