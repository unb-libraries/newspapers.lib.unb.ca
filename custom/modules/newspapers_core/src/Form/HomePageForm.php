<?php

namespace Drupal\newspapers_core\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
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
    $about_tab_class = " d-sm-none";
    if ($op == 'Search FullText') {
      $title_pane_class = $about_pane_class = NULL;
      $fulltext_pane_class = "";
    }
    else {
      $fulltext_pane_class = $about_pane_class = NULL;
      $title_pane_class = "show active";
    }

    $form = [];
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

    $blurb = "<p>New Brunswick Historical Newspapers Project provides researchers with
        unified access to UNB Libraries&apos; current and historical newspaper collections in all formats, from New
        Brunswick and across the world. Search and discover
        <a href=\"/print-titles\">print</a>, microform, and selected digital newspaper
        titles (including New Brunswick Historical Newspapers Online collection) available from UNB Libraries.</p>
        <p>Fulltext searching is available for our growing list of
        <a href=\"/digital-titles\">digitized titles</a>. Not all publications are available digitally.</p>
        <p>For more worldwide digital newspaper content, consult
        <a href=\"https://lib.unb.ca/eresources/collections/newspapers\" class=\"external\">
        UNB Libraries licensed electronic Newspaper collections</a>. UNB/STU login required.</p>";

    $form['blurb'] = [
      '#type' => 'markup',
      '#markup' => "<div class=\"d-none d-sm-block bg-unb-light border mb-4 p-3 rounded\">" . $blurb . "</div>",
    ];

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
      '#markup' => '<li class="nav-item" role="presentation">' . Link::fromTextAndUrl($this->t('Title Search'), $title_url)
        ->toString() . '</li>',
    ];
    $form['nav-tabs']['fulltext'] = [
      '#markup' => '<li class="nav-item" role="presentation">' . Link::fromTextAndUrl($this->t('Fulltext Search'), $fulltext_url)
        ->toString() . '</li>',
    ];
    $form['nav-tabs']['about'] = [
      '#markup' => '<li class="nav-item' . $about_tab_class . '" role="presentation">' . Link::fromTextAndUrl($this->t('About'), $about_url)
        ->toString() . '</li>',
    ];

    $form['tab-content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => [
          'search-wrapper',
        ],
        'class' => [
          'tab-content',
          'border',
          'p-4',
        ],
      ],
    ];
    $form['tab-content']['title'] = [
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
    $form['tab-content']['title']['input_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search for Newspaper Titles'),
      '#description' => $this->t('Search by title, location, publisher, notes, description or combination, i.e. Moncton 1932'),
    ];
    $form['tab-content']['title']['actions'] = [
      '#type' => 'actions',
    ];
    $form['tab-content']['title']['actions']['submit_title'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search/Browse Titles'),
    ];
    $form['tab-content']['title']['search-scope'] = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => [
        'class' => [
          'w-100',
          'mt-4',
        ],
      ],
    ];
    $form['tab-content']['title']['search-scope']['unlimited_region'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include newspapers <strong>published</strong> outside of New Brunswick'),
      '#default_value' => FALSE,
    ];

    $form['tab-content']['fulltext'] = [
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

    $form['tab-content']['fulltext']['search']['input_fulltext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search the fulltext of digitized newspapers'),
      '#description' => '<span class="mr-1">' . $this->t('Search for keywords within the fulltext of digitized 
        newspapers.') . '</span><a aria-controls="searchTips" aria-expanded="false" class="btn-link text-nowrap"
        data-toggle="collapse" href="#searchTips" role="button"><span aria-hidden="true"
        class="fas fa-question-circle mr-1"></span>Search tips</a>',
    ];
    $form['tab-content']['fulltext']['search']['actions'] = [
      '#type' => 'actions',
    ];
    $form['tab-content']['fulltext']['search']['actions']['submit_fulltext'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search FullText'),
      '#attributes' => [
        'class' => [
          'btn-unb-red',
        ],
      ],
    ];
    $form['tab-content']['fulltext']['tips'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="card collapse mt-3 w-100" id="searchTips">
          <div class="card-body">
            <h2 class="card-title sr-only">Search Term Guidelines</h2>
            <div class="description m-0">
              <ul class="mb-0 px-3">
                <li>Use uppercase AND operator to limit to all terms used. eg. <code>cattle AND Fredericton</code></li>
                <li>Use &quot;&quot; to do phrase searching. eg. <code>&quot;5000 pairs to select&quot;</code></li>
                <li>After searching, use the facets to limit by date, publication and location</li>
                <li>See current listing of
                  <a href="/digital-titles">digitally available titles</a></li>
              </ul>
            </div>
          </div>
        </div>',
    ];

    $form['tab-content']['about'] = [
      '#type' => 'container',
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
    $form['tab-content']['about']['wrapper'] = [
      '#type' => 'container',
    ];
    $form['tab-content']['about']['wrapper']['blurb'] = [
      '#type' => 'markup',
      '#markup' => $blurb,
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

    if ($op === 'Search FullText' && empty($value)) {
      $form_state->setErrorByName('input_fulltext', $this->t('Please provide a fulltext search term'));
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

    if ($op === 'Search FullText') {
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

}
