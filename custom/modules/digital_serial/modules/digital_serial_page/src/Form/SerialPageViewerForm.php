<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
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
      '#tag' => 'span',
      '#value' => $this->t('<span aria-hidden="true">« </span>Previous'),
      '#attributes' => [
        'aria-label' => ['Show previous page'],
      ],
    ];

    $current_page = $digital_serial_page->getActivePagerNo();
    $total_pages = $digital_serial_issue->getPageCount();
    $issue_missingp_note = $digital_serial_issue->getIssueMissingPages();

    $viewer_active_page_text = "Image $current_page of $total_pages";
    $viewer_active_pager_item = [
      '#type' => 'html_tag',
      '#tag' => 'span',
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
      '#tag' => 'span',
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

    $form['page_viewer']['back_link'] = [
      '#markup' => Link::fromTextAndUrl(
        $back_text, $url)
        ->toString(),
    ];
    $form['page_viewer']['back_link'];

    $form['page_viewer']['pager'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'mb-2',
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
      $form['page_viewer']['pager']['prev_page'] = $prev_link;
    }

    $form['page_viewer']['pager']['active'] = $viewer_active_pager_item;
    if (!empty($prev_next['next'])) {
      $prev_next['next']->setOptions($link_options);
      $next_link = [
        '#markup' => Link::fromTextAndUrl($next_text, $prev_next['next'])
          ->toString(),
      ];
      $form['page_viewer']['pager']['next_page'] = $next_link;
    }

    $form['page_viewer']['zoom'] = [
      '#type' => 'container',
      '#id' => 'seadragon-viewer',
      '#attributes' => [
        'aria-label' => 'Zoomable Page',
        'role' => 'region',
      ],
    ];
    $file = $digital_serial_page->get('page_image')->entity;
    $uri = $file->getFileUri();
    /* Deprecated D9.3.x: https://www.drupal.org/node/2940031 */
    /* $image_path = file_url_transform_relative(file_create_url($uri)); */
    $image_path = \Drupal::service('file_url_generator')->generateString($uri);

    $full_path = DRUPAL_ROOT . $image_path;
    $image_extension = pathinfo($full_path, PATHINFO_EXTENSION);
    $dzi_path = str_replace(".$image_extension", '.dzi', $full_path);

    $form['page_viewer']['metadata'] = $this->getMetadataRenderElement($digital_serial_title, $digital_serial_issue);
    $form['page_viewer']['metadata-footer'] = $this->getMetadataFooter($digital_serial_title, $digital_serial_issue);

    if (!empty($issue_missingp_note)) {
      $form['page_viewer']['missing_pages_note'] = [
        '#type' => 'container',
        'child' => [
          '#markup' => $this->t("Issue note / missing pages: @note.", [
            '@note' => $issue_missingp_note,
          ]),
        ],
        '#attributes' => [
          'class' => [
            'alert',
            'alert-info',
            'mt-4',
          ],
        ],
        '#weight' => 5,
      ];
    }

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
          'use_canvas' => $this->browserSupportsHugeCanvas($_SERVER['HTTP_USER_AGENT']),
        ],
      ],
    ];

    return $form;
  }

  /**
   * Determines if the current browser supports huge canvases.
   */
  private static function browserSupportsHugeCanvas($browser) {
    return !str_contains(strtolower($browser), 'safari') || str_contains(strtolower($browser), 'chrome');
  }

  /**
   * Filters out unwanted elements from the highlight keywords.
   *
   * @param array $keywords
   *   The keywords to filter.
   */
  private static function filterHighlightKeywords(array &$keywords): void {
    self::stripHighlightQuotes($keywords);
    $keywords = array_filter($keywords, [self, 'elementIsNotStopWord']);
  }

  /**
   * Generates rendered metadata for the serial page viewer.
   *
   * @param \Drupal\serial_holding\Entity\SerialTitleInterface $digital_serial_title
   *   The digital serial title entity.
   * @param \Drupal\serial_holding\Entity\SerialIssueInterface $digital_serial_issue
   *   The digital serial issue entity.
   *
   * @return array
   *   The render array for the serial page's metadata.
   */
  private function getMetadataRenderElement(SerialTitleInterface $digital_serial_title, SerialIssueInterface $digital_serial_issue): array {
    // URL object for Parent publication.
    try {
      $parent_title_url = $digital_serial_title
        ->getParentPublication()
        ->toUrl();
    }
    catch (EntityMalformedException $e) {
    }

    // Combination serial issue volume + issue number.
    $volume_issue_formatted = $this->t("Volume @volume, No. @issue",
      [
        '@volume' => !empty($digital_serial_issue->getIssueVol()) ? $digital_serial_issue->getIssueVol() : "n/a",
        '@issue' => !empty($digital_serial_issue->getIssueIssue()) ? $digital_serial_issue->getIssueIssue() : "n/s",
      ]
    );

    // Set up array for table element colgroup cols and row header/data cells.
    $colgroups = [
      [
        'data' => [
          [
            'width' => [
              '40%',
            ],
          ],
          [
            'width' => [
              '60%',
            ],
          ],
        ],
      ],
    ];
    $rows = [
      [
        'data' => [
          [
            'data' => $this->t('Publication Title'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          Link::fromTextAndUrl(
            $digital_serial_title
              ->getParentPublication()
              ->getTitle(),
            $parent_title_url
          ),
        ],
      ],
      [
        'data' => [
          [
            'data' => $this->t('Printed Title'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $digital_serial_issue->getIssueTitle(),
        ],
      ],
      [
        'data' => [
          [
            'data' => $this->t('Volume / Issue Number'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $volume_issue_formatted,
        ],
      ],
      [
        'data' => [
          [
            'data' => $this->t('Date'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $digital_serial_issue->get("issue_date")->value,
        ],
      ],
      [
        'data' => [
          [
            'data' => $this->t('Language'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $digital_serial_issue
            ->get("issue_language")
            ->getFieldDefinition()
            ->getSetting('allowed_values')[$digital_serial_issue->get("issue_language")->value],
        ],
      ],
      [
        'data' => [
          [
            'data' => $this->t('Media'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          ucfirst($digital_serial_issue->get("issue_media")->value),
        ],
      ],
      /*
      [
        'data' => [
          [
            'data' => $this->t('Download Image'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          Link::fromTextAndUrl(
            $file->getFilename(),
            Url::fromUri("internal:/{$image_path}")
          ),
        ],
      ],
       */
    ];

    // Return Form API 'table' element .
    return [
      '#type' => 'table',
      '#colgroups' => $colgroups,
      '#caption' => $this->t('Image Details'),
      '#rows' => $rows,
      '#attributes' => [
        'class' => [
          'my-4',
          'table',
        ],
      ],
      '#weight' => '1',
    ];
  }

  /**
   * Gets rendered footer metadata for the serial page viewer.
   *
   * @param \Drupal\serial_holding\Entity\SerialTitleInterface $dst
   *   The serial title entity.
   * @param \Drupal\serial_holding\Entity\SerialIssueInterface $dsi
   *   The serial issue entity.
   *
   * @return array
   *   The Report Info/Error metadata render array.
   */
  private function getMetadataFooter($dst, $dsi) {
    $footer_markup = '<div class="card-body d-flex flex-column flex-lg-row justify-content-between">';

    $webform_report = \Drupal::entityTypeManager()
      ->getStorage('webform')
      ->load('report_additional_info_errs');
    if ($webform_report != NULL) {
      // Retrieve Webform URL alias for Report webform.
      $report_url = $webform_report->getSetting('page_submit_path');
      $report_url_options = [
        'query' => [
          'newspaper' => $dst->getParentPublication()->getTitle() . ': ' . $dsi->getDisplayTitle(),
          'subdirectory' => 'serials/' . $dst->id() . '/issues/' . $dsi->id(),
        ],
      ];
      $footer_markup .= '<div class="link-report mb-2 mb-lg-0">
        <a href="' . Url::fromUri('base:' . $report_url, $report_url_options)->toString() .
        '">Report additional information or errors<span class="fa-solid fa-marker fa-sm ml-1" aria-hidden="true"></span>
        </a>
      </div>';
    }

    $footer_markup .= '<div class="link-external-terms">
        <a href="https://lib.unb.ca/archives/policies/terms">Terms of Use for the NBHNP
        <span class="fa-solid fa-external-link-alt fa-sm ml-1" aria-description="link leads to external site"></span>
        </a>
      </div>
    </div>';

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'card',
        ],
      ],
      '#weight' => 1,
      'child' => [
        '#markup' => $footer_markup,
      ],
    ];
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
  private static function elementIsNotStopWord($element): bool {
    $stop_words = [
      'a',
      'an',
      'and',
      'but',
      'for',
      'in',
      'it',
      'my',
      'or',
      'our',
      'the',
      'their',
      'to',
      'your',
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
