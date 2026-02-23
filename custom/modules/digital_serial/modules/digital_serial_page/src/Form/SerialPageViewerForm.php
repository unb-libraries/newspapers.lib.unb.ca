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
use Drupal\file\Entity\File;

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

    // Citation: $ParentTitle $vol, no. $iss, M d, Y: [$page#]. NBHNP. $url.
    $volume_issue_citation_format = $this->t("@volume, no. @issue,",
      [
        '@volume' => !empty($digital_serial_issue->getIssueVol()) ? $digital_serial_issue->getIssueVol() : "n/a",
        '@issue' => !empty($digital_serial_issue->getIssueIssue()) ? $digital_serial_issue->getIssueIssue() : "n/s",
      ]
    );
    global $base_url;

    if (strpos($referrer, 'search') !== FALSE) {
      $back_text = $this->t('Back to search results');
      $url = Url::fromUri($referrer);
    }
    else {
      $back_text = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Back to Digital Issues'),
      ];
      $uri = "internal:/serials/browse/{$digital_serial_title->id()}";
      $url = Url::fromUri($uri);
    }

    $form['page_viewer']['zoom'] = [
      '#type' => 'container',
      '#id' => 'seadragon-viewer',
      '#weight' => 50,
      '#attributes' => [
        'aria-label' => 'Zoomable Page',
        'role' => 'region',
      ],
    ];

    $form['page_viewer']['nav'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'controls',
          'align-items-center',
          'd-flex',
          'justify-content-between',
          'border',
          'mb-0',
          'p-1',
        ],
      ],
    ];
    $form['page_viewer']['nav']['toolbar'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'mb-0',
          'text-center'
        ],
        'id' => 'toolbarDiv',
      ],
    ];
    $form['page_viewer']['nav']['pager'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'align-items-center',
          'btn-sm',
          'my-0',
          'text-center',
        ],
        'aria-label' => 'Page viewer controls',
        'role' => 'group',
      ],
      '#weight' => 0,
    ];

    $form['page_viewer']['nav']['pager']['previous'] = [];
    $prev_link_options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-link',
          'p-1',
        ],
        'id' => 'previous',
        'title' => $this->t('Previous Image'),
      ],
    ];
    $current_page = $digital_serial_page->getActivePagerNo();
    $total_pages = $digital_serial_issue->getPageCount();
    $prev_text = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => [
        'aria-label' => ['Previous image'],
        'class' => [
          'fa-solid',
          'fa-backward',
        ],
      ],
    ];
    $viewer_active_page_text = "Page <span class=\"text-nowrap\">$current_page of $total_pages</span>";
    $viewer_active_pager_item = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      "#value" => $viewer_active_page_text,
      "#attributes" => [
        'class' => [
          'ml-1',
        ],
        'id' => [
          'pageIndicator',
        ],
      ],
    ];
    $next_text = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => [
        'aria-label' => ['Next image'],
        'class' => [
          'fa-solid',
          'fa-forward',
        ]
      ],
    ];
    if (!empty($prev_next['previous'])) {
      $prev_next['previous']->setOptions($prev_link_options);
      $prev_link = [
        '#markup' => Link::fromTextAndUrl($prev_text, $prev_next['previous'])
          ->toString(),
      ];
      $form['page_viewer']['nav']['pager']['prev_page'] = $prev_link;
    }
    $form['page_viewer']['nav']['pager']['active'] = $viewer_active_pager_item;
    $next_link_options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-link',
          'btn-sm',
          'p-1',
        ],
        'id' => 'next',
        'title' => $this->t('Next image'),
      ],
    ];
    if (!empty($prev_next['next'])) {
      $prev_next['next']->setOptions($next_link_options);
      $next_link = [
        '#markup' => Link::fromTextAndUrl($next_text, $prev_next['next'])
          ->toString(),
      ];
      $form['page_viewer']['nav']['pager']['next_page'] = $next_link;
    }

    $form['page_viewer']['nav']['details'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('<span class="fa-solid fa-circle-info mr-1" 
        aria-hidden="true"></span><span class="d-none d-md-inline">Issue</span> Details'),
      '#attributes' => [
        'class' => [
          'btn',
          'btn-sm',
          'btn-link',
          'mx-2',
        ],
        'type' => 'button',
        'data-target' => '#detailsWrapper',
        'data-toggle' => 'collapse',
        'aria-expanded' => 'false',
        'aria-controls' => 'detailsWrapper',
      ],
      '#weight' => 20,
    ];

    // Get render array for the downloads visibility toggle section.
    $file = $digital_serial_page->get('page_image')->entity;
    $uri = $file->getFileUri();
    $image_path = \Drupal::service('file_url_generator')->generateString($uri);
    $downloads = $this->getRenderedDownloadSection($digital_serial_page);
    if (!empty($downloads)) {
      $form['page_viewer']['download-wrapper'] = $downloads;
      // Page viewer > navigation > Download button.
      $form['page_viewer']['nav']['download'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => t('<span class="fa-solid fa-download mr-1"></span>Download'),
        '#attributes' => [
          'class' => [
            'btn',
            'btn-sm',
            'btn-link',
            'mx-2',
          ],
          'type' => 'button',
          'data-target' => '#downloadsWrapper',
          'data-toggle' => 'collapse',
          'aria-expanded' => 'false',
          'aria-controls' => 'downloadsWrapper',
        ],
        '#weight' => 30,
      ];
    }

    // Get Cite button markup + rendered citation modal section.
    $citation_btn_markup = '<button type="button" class="btn btn-link btn-sm"
      data-target="#citation-modal" data-toggle="modal">
      <span class="fa-solid fa-quote-left mr-1" aria-hidden="true"></span>Cite</button>';
    $page_number = $digital_serial_page->getActivePagerNo(); /* Duplicate of $current_page */
    $cited_title = $digital_serial_issue->getIssueTitle();
    $citation_text = '<em>' . $digital_serial_title->getParentPublication()->getTitle() .
      "</em> $volume_issue_citation_format " .
      date("F d, Y", strtotime($digital_serial_issue->get("issue_date")->value)) .
      ": [$page_number]. <em>" .
      \Drupal::config('system.site')->get('name') . '</em>, accessed ' .
      date_create('now')->format('F d, Y') . ', <span class="text-word-break">' .
      $base_url . \Drupal::service('path.current')->getPath() . '</span>.';
    $citation_render_array = _newspapers_core_get_citation_render_array($cited_title, $citation_text);
    $citation_modal = \Drupal::service('renderer')->render($citation_render_array);
    $form['page_viewer']['citation'] = [
      '#markup' => $citation_modal,
    ];

    // Page viewer > navigation > Cite button.
    $form['page_viewer']['nav']['citation'] = [
      '#markup' =>  $citation_btn_markup,
      '#allowed_tags' => [
        'button',
        'span',
      ],
      '#prefix' => '<div class="citation-wrapper mx-2">',
      '#suffix' => '</div>',
      '#weight' => 40,
    ];

    // Get render array for the social sharing links nav section.
    $renderer = \Drupal::service('renderer');
    $social_render_array = _newspapers_core_get_rendered_social_links(
      $digital_serial_title
        ->getParentPublication()
        ->getTitle(),
      27
    );
    $social_rendered = $renderer->render($social_render_array);
    // Page viewer > navigation > Social Share buttons.
    $form['page_viewer']['nav']['social'] = [
      '#markup' => $social_rendered,
      '#prefix' => '<div class="social-wrapper text-center">',
      '#suffix' => '</div>',
      '#weight' => 50,
    ];

    // Get Issue Details visibility toggle section.
    $form['page_viewer']['metadata-body'] = $this->getMetadataBody($digital_serial_title, $digital_serial_issue, $current_page, $digital_serial_page);

    // Get Page Viewer Footer links section.
    $form['page_viewer']['metadata-footer'] = $this->getMetadataFooter($digital_serial_title, $digital_serial_issue);

    // Determine if we're using DZI or the plain old image.
    $dzi_uri = $digital_serial_page->getDziUri();
    if (!empty($dzi_uri)) {
      $tile_sources = $dzi_uri['path'];
    }
    else {
      $tile_sources = json_encode(
        [
          'type' => 'image',
          'url' => $image_path,
        ]
      );
    }

    $back_link_options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-sm',
          'btn-link',
          'py-1',
        ],
        'id' => 'backLink',
      ],
    ];
    $url->setOptions($back_link_options);
    $form['page_viewer']['back_link'] = [
      '#markup' => Link::fromTextAndUrl(
        $back_text, $url)
        ->toString(),
      '#weight' => 50,
    ];

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
        'digital_serial_page/openseadragon_viewer_accessibility',
        'newspapers_core/copy_citation',
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
    $keywords = array_filter($keywords, [self::class, 'elementIsNotStopWord']);
  }

  /**
   * Generates rendered metadata for the serial page viewer.
   *
   * @param \Drupal\serial_holding\Entity\SerialTitleInterface $digital_serial_title
   *   The digital serial title entity.
   * @param \Drupal\serial_holding\Entity\SerialIssueInterface $digital_serial_issue
   *   The digital serial issue entity.
   * @param string $page_number
   *   The active issue/page pager number.
   *
   * @return array
   *   The render array for the serial page's metadata.
   */
  private function getMetadataBody(
    SerialTitleInterface $digital_serial_title,
    SerialIssueInterface $digital_serial_issue,
    string $page_number,
    SerialPageInterface $digital_serial_page
  ): array {
    // URL object for Parent publication.
    try {
      $parent_title_url = $digital_serial_title
        ->getParentPublication()
        ->toUrl();
    }
    catch (EntityMalformedException $e) {
    }

    $issue_printed_title = $digital_serial_issue->getIssueTitle();
    $issue_missingp_note = $digital_serial_issue->getIssueMissingPages();
    $issue_errata = $digital_serial_issue->getIssueErrata();
    $issue_edition = $digital_serial_issue->getIssueEdition();

    // Combination serial issue volume + issue number.
    $volume_issue_metadata_format = $this->t("Volume @volume, No. @issue",
      [
        '@volume' => !empty($digital_serial_issue->getIssueVol()) ? $digital_serial_issue->getIssueVol() : "n/a",
        '@issue' => !empty($digital_serial_issue->getIssueIssue()) ? $digital_serial_issue->getIssueIssue() : "n/s",
      ]
    );

    // Set up array for table element colgroup cols.
    $colgroups = [
      [
        'data' => [
          [
            'width' => [
              '21%',
            ],
          ],
          [
            'width' => [
              '79%',
            ],
          ],
        ],
      ],
    ];

    // Initialize optional row arrays.
    $rows_title_misc = $row_missingp = $row_errata = $row_edition = $row_download = [];

    // Set up arrays for table element row header/data cells.
    $row_pub_title = [
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
    ];

    $title_hist_render_array = _newspapers_core_get_rendered_title_history(
      $digital_serial_title->getParentPublication(),
      FALSE,
      4
    );

    /* Only include optional broad title history row if >1 item in render array */
    if (count($title_hist_render_array['#children'][0]['#items']) > 1) {
      $title_history = \Drupal::service('renderer')->render($title_hist_render_array);
    }
    $rows_title_misc = [
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
    ];
    if(!empty($title_history)) {
      $row_title_hist = [
        'data' => [
          [
            'data' => $this->t('Publication Family'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $title_history,
        ],
      ];
      array_unshift($rows_title_misc, $row_title_hist);
    }

    $row_volume = [
      [
        'data' => [
          [
            'data' => $this->t('Volume / Issue Number'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $volume_issue_metadata_format,
        ],
      ],
    ];

    // Optional edition.
    if (!empty($issue_edition)) {
      $row_edition = [
        [
          'data' => [
            [
              'data' => $this->t('Edition'),
              'header' => TRUE,
              'scope' => 'row',
            ],
            $issue_edition,
          ],
        ],
      ];
    }

    $row_date = [
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
    ];

    $row_place = [
      [
        'data' => [
          [
            'data' => $this->t('Place of Publication'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $this->getPlacePublication($digital_serial_title),
        ],
      ],
    ];

    // More optional fields.
    if (!empty($issue_missingp_note)) {
      $row_missingp = [
        [
          'data' => [
            [
              'data' => $this->t('Missing Pages'),
              'header' => TRUE,
              'scope' => 'row',
            ],
            $issue_missingp_note,
          ],
        ],
      ];
    }
    if (!empty($issue_errata)) {
      $row_errata = [
        [
          'data' => [
            [
              'data' => $this->t('Errata'),
              'header' => TRUE,
              'scope' => 'row',
            ],
            $issue_errata,
          ],
        ],
      ];
    }

    $rows_misc = [
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
    ];

    // Download row moved to page viewer navigation.
    // Cite this image row moved to page viewer navigation.

    $renderer = \Drupal::service('renderer');
    $social_render_array = _newspapers_core_get_rendered_social_links(
      $digital_serial_title
        ->getParentPublication()
        ->getTitle(),
       24
    );
    $social_rendered = $renderer->render($social_render_array);
    $row_social = [
      [
        'data' => [
          [
            'data' => $this->t('Share'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $social_rendered,
        ],
      ],
    ];

    // Return Form API 'table' element .
    return [
      '#type' => 'table',
      '#colgroups' => $colgroups,
      '#caption' => $this->t('Issue Details'),
      '#rows' => array_merge(
        $row_pub_title,
        $row_place,
        $rows_title_misc,
        $row_volume,
        $row_edition,
        $row_date,
        $row_missingp,
        $row_errata,
        $rows_misc,
        $row_download,
      ),
      // Moved to toolbar: $row_social,         $row_citation,
      '#attributes' => [
        'class' => [
          'table',
          'table-sm',
        ],
      ],
      '#prefix' => '<div id="detailsWrapper" class="border mb-3 collapse">',
      '#suffix' => '</div>',
    ];
  }

  /**
   * Builds the PDF HTML link for the image.
   */
  private function buildPdfDownloadLinkHtml($digital_serial_page) {
    $pdf_uri = $digital_serial_page->getPdfUri();
    if (empty($pdf_uri)) {
      return '';
    }
    $pdf_file_name = basename($pdf_uri['file']);

    $pdf_download_link_options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-link',
        ],
        'download' => TRUE,
      ],
    ];

    return Link::fromTextAndUrl(
      Markup::create(
        '<span class="fa-solid fa-file-pdf mr-1" aria-hidden="true"></span>' . $pdf_file_name .
        $this->getImageSizeDisplay($pdf_uri['file'])
      ),
      Url::fromUserInput($pdf_uri['path'], $pdf_download_link_options),
    )->toString();
  }

  /**
   * Retrieves the formatted image size display for the metadata table.
   */
  private function getImageSizeDisplay($file_path) {
    if (filesize($file_path) < 1) {
      return '';
    }
    return '<span class="text-muted filesize">(' .
    $this->getFileSizeHuman($file_path) .
    'B)</span>';
  }

  /**
   * Converts a file size in bytes to a human-readable format.
   */
  private function humanFilesize($bytes, $decimals = 1) {
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . @$sz[$factor];
  }

  /**
   * Gets the human-readable file size for a file.
   */
  private function getFileSizeHuman($file_path) {
    $file_size = filesize($file_path);
    return $this->humanFilesize($file_size);
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
  private function getMetadataFooter(SerialTitleInterface $dst, SerialIssueInterface $dsi): array {
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
        '">Report additional information or errors<span class="fa-solid fa-pen-to-square fa-sm ml-1" aria-hidden="true"></span>
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
      '#weight' => 50,
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
      '',
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
   * Gets the Place of Publication value for the issue.
   *
   * @param \Drupal\serial_holding\Entity\SerialTitleInterface $dst
   *    The digital serial title entity.
   *
   * @return string
   *    The issue's place of publication.
   */
  private function getPlacePublication(SerialTitleInterface $dst): string {
    $place_publication = $dst
      ->getParentPublication()
      ->get("field_place_of_publication")
      ->getValue()[0];

    return $place_publication["locality"] . ", " . $place_publication["administrative_area"];
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

  public function getRenderedDownloadSection (SerialPageInterface $digital_serial_page) {
    $page_image_file = $digital_serial_page->get('page_image')->entity;
    $uri = $page_image_file->getFileUri();

    $image_path = \Drupal::service('file_url_generator')->generateString($uri);
    $image_download_path = DRUPAL_ROOT . $image_path;

    // Create download image row IF page entity|digital image|file obtainable.
    if (file_exists($image_download_path)) {
      $image_download_uri = \Drupal::service('file_url_generator')
        ->generateAbsoluteString($page_image_file->getFileUri());
      $image_download_link_options = [
        'attributes' => [
          'class' => [
            'btn',
            'btn-link',
          ],
          'download' => TRUE,
        ],
      ];

      $download_link = Link::fromTextAndUrl(
        Markup::create(
          '<span class="fa-solid fa-file-image mr-1" aria-hidden="true"></span>' . $page_image_file->getFilename() .
          $this->getImageSizeDisplay($image_download_path)
        ),
        Url::fromUri($image_download_uri, $image_download_link_options
        ),

      );

      $download_items = [$download_link->toString()];
      $pdf_download_html = $this->buildPdfDownloadLinkHtml($digital_serial_page);
      if (!empty($pdf_download_html)) {
        $download_items[] = $pdf_download_html;
      }
      $download_html_list = '<ul class="list-inline list-unstyled">';
      foreach ($download_items as $download_item) {
        $download_html_list .= "<li class='list-inline-item mx-3'>$download_item</li>";
      }
      $download_html_list .= '</ul>';

      return [
        '#type' => 'fieldset',
        '#title' => $this->t('Download & Save Options'),
        '#attributes' => [
          'class' => [
            'mb-0',
            'mt-3',
            'text-center'
          ],
        ],
        '#prefix' => '<div id="downloadsWrapper" class="border mb-3 collapse">',
        '#suffix' => '</div>',
        '#weight' => 10,
        'child' => [
          '#markup' =>  $download_html_list,
        ],
      ];

      /*$row_download = [
        [
          'data' => [
            [
              'data' => $this->t('Downloads'),
              'header' => TRUE,
              'scope' => 'row',
            ],
            [
              'data' => [
                '#markup' => $download_html_list,
              ],
            ],
          ],
        ],
      ];*/

    }
  }
}
