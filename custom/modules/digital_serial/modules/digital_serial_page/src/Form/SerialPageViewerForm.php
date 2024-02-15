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

    $prev_text = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('<span aria-hidden="true">« </span>previous'),
      '#attributes' => [
        'aria-label' => ['Show previous page'],
      ],
    ];

    $current_page = $digital_serial_page->getActivePagerNo();
    $total_pages = $digital_serial_issue->getPageCount();

    $viewer_active_page_text = "$current_page of $total_pages";
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
      '#value' => $this->t('next<span aria-hidden="true"> »</span>'),
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
        '#value' => 'Browse digital issues',
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

    $form['page_viewer']['metadata-body'] = $this->getMetadataBody($digital_serial_title, $digital_serial_issue, $current_page, $file, $full_path);
    $form['page_viewer']['metadata-footer'] = $this->getMetadataFooter($digital_serial_title, $digital_serial_issue);

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
   * @param \Drupal\file\Entity\File $page_image_file
   *   The uploaded image file for the digital serial page.
   * @param string $image_download_path
   *   The downloadable image full path.
   *
   * @return array
   *   The render array for the serial page's metadata.
   */
  private function getMetadataBody(SerialTitleInterface $digital_serial_title,
                                            SerialIssueInterface $digital_serial_issue,
                                            string $page_number,
                                            File $page_image_file,
                                            string $image_download_path): array {
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
    $volume_issue_citation_format = $this->t("@volume, no. @issue,",
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

    // Initialize optional row arrays.
    $row_printed_title = $row_missingp = $row_errata = $row_edition = $row_download = [];

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
    if (!empty($issue_printed_title)) {
      $row_printed_title = [
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
      $pdf_download_html = $this->buildPdfDownloadLinkHtml($image_download_path, $image_download_uri);
      if (!empty($pdf_download_html)) {
        $download_items[] = $pdf_download_html;
      }
      $download_html_list = '<ul class="d-inline list-unstyled">';
      foreach ($download_items as $download_item) {
        $download_html_list .= "<li>$download_item</li>";
      }
      $download_html_list .= '</ul>';

      $row_download = [
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
      ];
    }

    // Citation: $ParentTitle $vol, no. $iss, M d, Y: [$page#]. NBHNP. $url.
    global $base_url;
    $citation_btn_markup = '<button type="button" class="btn btn-link"
      data-target="#citation-modal" data-toggle="modal">
      <span class="fa-solid fa-quote-left mr-1" aria-hidden="true"></span>CMS - Chicago Manual of Style</button>';
    $cited_title = $digital_serial_issue->getIssueTitle();
    $citation_text = '<em>' . $digital_serial_title->getParentPublication()->getTitle() .
      "</em> $volume_issue_citation_format " .
      date("F d, Y", strtotime($digital_serial_issue->get("issue_date")->value)) .
      ": [$page_number]. <em>" .
      \Drupal::config('system.site')->get('name') . '</em>, accessed ' .
      date_create('now')->format('F d, Y') . ', <span class="text-word-break">' .
      $base_url . \Drupal::service('path.current')->getPath() . '</span>.';
    $citation_render_array = _newspapers_core_get_citation_render_array($citation_btn_markup, $cited_title, $citation_text);
    $citation = \Drupal::service('renderer')->render($citation_render_array);

    $row_citation = [
      [
        'data' => [
          [
            'data' => $this->t('Cite this Image'),
            'header' => TRUE,
            'scope' => 'row',
          ],
          $citation,
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
        $row_printed_title,
        $row_volume,
        $row_edition,
        $row_date,
        $row_missingp,
        $row_errata,
        $rows_misc,
        $row_download,
        $row_citation,
      ),
      '#attributes' => [
        'class' => [
          'my-4',
          'table',
          'table-sm',
        ],
      ],
      '#weight' => '1',
    ];
  }

  /**
   * Builds the PDF HTML link for the image.
   */
  private function buildPdfDownloadLinkHtml($image_file_path, $image_download_uri) {
    $image_file_components = pathinfo($image_file_path);
    $pdf_file_name = $image_file_components['filename'] . '.pdf';
    $pdf_file_path = $image_file_components['dirname'] . '/pdf/' . $pdf_file_name;

    if (file_exists($pdf_file_path) === FALSE) {
      return '';
    }

    $pdf_download_uri = str_replace(
      $image_file_components['filename'] . '.jpg',
      'pdf/' . $image_file_components['filename'] . '.pdf',
      $image_download_uri
    );
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
        $this->getImageSizeDisplay($pdf_file_path)
      ),
      Url::fromUri($pdf_download_uri, $pdf_download_link_options),
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
