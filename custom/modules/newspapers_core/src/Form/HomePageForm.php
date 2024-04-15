<?php

namespace Drupal\newspapers_core\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Front page search form.
 *
 * Provides tabbed Search Form for searching newspaper title metadata or
 * content within scanned pages.
 */
class HomePageForm extends FormBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'newspapers_core_homepage';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Configure appropriate active tab/pane classes.
    $user_input = $form_state->getUserInput();
    $op = $user_input['op'] ?? NULL;
    if ($op == 'Search FullText') {
      $title_pane_class = $about_pane_class = NULL;
      $fulltext_pane_class = "";
    }
    else {
      $fulltext_pane_class = $about_pane_class = NULL;
      $title_pane_class = "show active";
    }

    // Markup snippets.
    $about_markup = '<p>The <a href="project">New Brunswick Historical Newspapers Project</a> provides
      researchers with unified access to UNB Libraries&apos; current and historical newspaper collections in all formats,
      from New Brunswick and across the world. Search and discover
      <a href="/print-titles">print</a>, microform, and selected digital newspaper
      titles available from UNB Libraries. This site also acts as an index for all known New Brunswick
      newspapers and listing of where copies are located throughout the province and beyond.</p>
      <p>For more worldwide digital newspaper content, consult
      <a href="https://lib.unb.ca/eresources/collections/newspapers" class="external">
      UNB Libraries licensed electronic Newspaper collections</a>
      <span class="text-muted">(UNB/STU login required)</span>.</p>
      <p class="mb-0">We appreciate your interest in this project and we would be
      <a href="/contact">happy to hear from you<span class="fa-solid fa-comment-dots fa-sm ml-1" aria-hidden="true"></span></a>.
      </p>';

    $searchtips_markup = '<aside class="search-tips mt-3 w-100">
          <p class="h3 mb-1">Search Tips:</p>
          <ul class="mb-0">
            <li>Use uppercase AND operator to limit to all terms used. eg. <code>cattle AND Fredericton</code></li>
            <li>Use quotation marks to do phrase searching. eg. <code>&quot;5000 pairs to select&quot;</code></li>
            <li>After searching, use the filters to limit by date, publication and location</li>
          </ul>
        </aside>';

    $stats_markup = $this->getStatsMarkup();

    // Tab urls.
    $title_url = Url::fromUri("internal:");
    $title_link_options = [
      'attributes' => [
        'id' => [
          'tab-title',
        ],
        'class' => [
          'nav-link',
          'active',
        ],
        'data-toggle' => [
          'tab',
        ],
        'role' => [
          'tab',
        ],
        'aria-controls' => [
          'title',
        ],
        'aria-selected' => [
          'true',
        ],

      ],
      'fragment' => 'title',
    ];
    $title_url->setOptions($title_link_options);

    $fulltext_url = Url::fromUri("internal:");
    $fulltext_link_options = [
      'attributes' => [
        'id' => [
          'tab-fulltext',
        ],
        'class' => [
          'nav-link',
        ],
        'role' => [
          'tab',
        ],
        'data-toggle' => [
          'tab',
        ],
        'aria-controls' => [
          'fulltext',
        ],
        'aria-selected' => [
          'false',
        ],
      ],
      'fragment' => 'fulltext',
    ];
    $fulltext_url->setOptions($fulltext_link_options);

    $about_url = Url::fromUri("internal:");
    $about_link_options = [
      'attributes' => [
        'id' => [
          'tab-about',
        ],
        'class' => [
          'nav-link',
        ],
        'role' => [
          'tab',
        ],
        'data-toggle' => [
          'tab',
        ],
        'aria-controls' => [
          'about',
        ],
        'aria-selected' => [
          'false',
        ],

      ],
      'fragment' => 'about',
    ];
    $about_url->setOptions($about_link_options);

    // Tabbed form content.
    $form = [];

    // Navigation tabs.
    $form['nav-tabs'] = [
      '#type' => 'html_tag',
      '#tag' => 'ul',
      '#attributes' => [
        'class' => [
          'nav',
          'nav-tabs',
        ],
        'role' => [
          'tablist',
        ],
      ],
    ];
    $form['nav-tabs']['title'] = [
      '#markup' => '<li class="nav-item" role="presentation">' .
      Link::fromTextAndUrl($this->t('Title Search'), $title_url)->toString() .
      '</li>',
    ];
    $form['nav-tabs']['fulltext'] = [
      '#markup' => '<li class="nav-item" role="presentation">' .
      Link::fromTextAndUrl($this->t('Fulltext<span class="d-none d-md-inline"> Search</span>'),
        $fulltext_url)->toString() .
      '</li>',
    ];
    $form['nav-tabs']['about'] = [
      '#markup' => '<li class="nav-item" role="presentation">' .
      Link::fromTextAndUrl(
        Markup::create($this->t('About<span class="d-none d-md-inline"> the Project</span><span
        class="fa-solid fa-circle-question ml-1"></span>')), $about_url
      )->toString() . '</li>',
    ];

    // Tab panel wrapper for toggleable panes & adjacent stats section.
    $form['panel-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'border',
          'd-flex',
          'flex-column',
          'flex-lg-row',
        ],
        'id' => [
          'panel-wrapper',
        ],
      ],
    ];

    // Wrapper for toggleable tab panes.
    $form['panel-wrapper']['tab-content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => [
          'search-wrapper',
        ],
        'class' => [
          'd-flex',
          'flex-grow-1',
          'p-4',
          'tab-content',
        ],
      ],
    ];

    // Title Search tab pane.
    $form['panel-wrapper']['tab-content']['title'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tab-pane',
          'fade',
          $title_pane_class,
        ],
        'id' => [
          'title',
        ],
        'role' => [
          'tabpanel',
        ],
        'aria-labelledby' => [
          'tab-title',
        ],
      ],
    ];
    $form['panel-wrapper']['tab-content']['title']['input_title'] = [
      '#type' => 'search',
      '#title' => $this->t('Search for newspaper titles'),
      '#description' => $this->t('Search by title, location, publisher, notes, description or combination. eg. <code>Moncton 1932</code>'),
    ];
    $form['panel-wrapper']['tab-content']['title']['actions'] = [
      '#type' => 'actions',
    ];
    $form['panel-wrapper']['tab-content']['title']['actions']['submit_title'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search/Browse Titles'),
    ];
    $form['panel-wrapper']['tab-content']['title']['search-scope'] = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => [
        'class' => [
          'w-100',
          'mt-4',
        ],
      ],
    ];
    $form['panel-wrapper']['tab-content']['title']['search-scope']['unlimited_region'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include newspapers published <strong>outside</strong> of New Brunswick'),
      '#default_value' => FALSE,
    ];

    // Fulltext Search tab pane.
    $form['panel-wrapper']['tab-content']['fulltext'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tab-pane',
          'fade',
          $fulltext_pane_class,
        ],
        'id' => [
          'fulltext',
        ],
        'role' => [
          'tabpanel',
        ],
        'aria-labelledby' => [
          'tab-fulltext',
        ],
      ],
    ];
    $form['panel-wrapper']['tab-content']['fulltext']['search']['input_fulltext'] = [
      '#type' => 'search',
      '#title' => $this->t('Search the fulltext of digitized newspapers'),
      '#description' => '<span class="mr-1">' .
      $this->t('Search for 1 or more keywords within the fulltext of digitized newspapers') . '</span>',
    ];
    $form['panel-wrapper']['tab-content']['fulltext']['search']['actions'] = [
      '#type' => 'actions',
    ];
    $form['panel-wrapper']['tab-content']['fulltext']['search']['actions']['submit_fulltext'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search Fulltext'),
      '#attributes' => [
        'class' => [
          'btn-unb-red',
          'px-4',
        ],
      ],
    ];
    $form['panel-wrapper']['tab-content']['fulltext']['tips'] = [
      '#type' => 'markup',
      '#markup' => $searchtips_markup,
    ];

    // About the Project tab pane.
    $form['panel-wrapper']['tab-content']['about'] = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => [
        'class' => [
          'tab-pane',
          'fade',
          $about_pane_class,
        ],
        'id' => [
          'about',
        ],
        'role' => [
          'tabpanel',
        ],
        'aria-labelledby' => [
          'tab-about',
        ],
      ],
    ];
    $form['panel-wrapper']['tab-content']['about']['wrapper'] = [
      '#type' => 'container',
    ];
    $form['panel-wrapper']['tab-content']['about']['wrapper']['blurb'] = [
      '#type' => 'markup',
      '#markup' => $about_markup,
    ];

    // Always visible digital stats section.
    $form['panel-wrapper']['stats'] = [
      '#type' => 'markup',
      '#markup' => $stats_markup,
    ];

    $form['#cache'] = [
      'keys' => ['newspapers_frontpage'],
      'contexts' => ['url'],
      'max-age' => Cache::PERMANENT,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    /* Enforce fulltext search term */
    $value = (string) $values['input_fulltext'];
    $op = (string) $form_state->getValue('op');

    if ($op === 'Search Fulltext' && empty($value)) {
      $form_state->setErrorByName('input_fulltext', $this->t('Please provide a fulltext search term and try again'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $op = (string) $form_state->getValue('op');
    $input_fulltext = (string) $values['input_fulltext'];
    $input_title = (string) $values['input_title'];

    if ($op === 'Search Fulltext') {
      $query = $this->getQueryFromValue($input_fulltext);
      $form_state->setRedirectUrl(
        Url::fromUri("internal:/page-search?fulltext=$query")
      );
    }
    elseif ($op === 'Search/Browse Titles') {
      $query = $this->getQueryFromValue($input_title);
      $unlimited_search = $form_state->getValue('unlimited_region') ?? NULL;
      if (!$unlimited_search) {
        // Append encoded query argument to enable Province/State facet for N.B.
        // &f[0]=province_state:New Brunswick.
        $query .= "&f%5B0%5D=province_state%3ANew%20Brunswick";
      }

      if (empty($query)) {
        $form_state->setRedirectUrl(
          Url::fromUri("internal:/search")
        );
      }
      else {
        $form_state->setRedirectUrl(
          Url::fromUri("internal:/search?query=$query")
        );
      }
    }

  }

  /**
   * Get the query from a form state value.
   *
   * @param object $value
   *   The form state value.
   *
   * @return string
   *   The string value of the form state value.
   */
  private function getQueryFromValue($value) {
    return (string) $value;
  }

  /**
   * Determine the markup for the digital title stats table.
   *
   * @return string
   *   The string value of the digital title stats HTML markup.
   */
  private function getStatsMarkup(): string {
    // Fetch digital serial counts for the stats section.
    $database = \Drupal::database();
    try {
      $pages_row_count = number_format($database
        ->select('digital_serial_page', 'p')
        ->countQuery()
        ->execute()
        ->fetchField()
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $pages_row_count = 'Unknown';
    }
    try {
      $issues_row_count = number_format($database
        ->select('digital_serial_issue', 'i')
        ->countQuery()
        ->execute()
        ->fetchField()
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $issues_row_count = 'Unknown';
    }
    try {
      $sub_query = $database->select('digital_serial_issue', 'i')->fields('i', ['parent_title']);
      $query = $database->select('digital_serial_title', 't');
      $titles_row_count = number_format($query
        ->condition('t.id', $sub_query, 'IN')
        ->countQuery()
        ->execute()
        ->fetchField()
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('type')->error($e->getMessage());
      $titles_row_count = 'Unknown';
    }

    return '<aside class="bg-light col-lg-4 p-3 stats">' .
      '<table class="table table-sm mb-3"><caption class="visually-hidden">Digitiazed Content</caption>' .
      '<colgroup><col width="25%"></colgroup><tbody>' .
      '<tr><th class="text-uppercase" scope="row">Digital<br>titles</th><td>' . $titles_row_count . '</td></tr>' .
      '<tr><th class="text-uppercase" scope="row">Digital<br>issues</th><td>' . $issues_row_count . '</td></tr>' .
      '<tr><th class="text-uppercase" scope="row">Total<br>pages</th><td>' . $pages_row_count . '</td></tr>' .
      '</tbody></table>' .
      '<p class="mb-1"><a href="/digital-titles" class="ml-1">Current list of digitized titles</a></p>' .
      '</aside>';
  }

}
