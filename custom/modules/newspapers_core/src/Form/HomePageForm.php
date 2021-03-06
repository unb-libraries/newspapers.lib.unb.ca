<?php

namespace Drupal\newspapers_core\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * HomePageForm object.
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
    $op = isset($user_input['op']) ? $user_input['op'] : NULL;
    $about_tab_class = " visible-xs";
    if ($op == 'Search FullText') {
      $title_tab_class = $title_pane_class = $about_pane_class = NULL;

      $fulltext_tab_class = " active";
      $fulltext_pane_class = "active in";
    }
    else {
      $fulltext_tab_class = $fulltext_pane_class = $about_pane_class = NULL;
      $title_tab_class = " active";
      $title_pane_class = "active in";
    }

    $form = [];
    $title_url = Url::fromUri("internal:/");
    $title_link_options = [
      'attributes' => [
        'id' => [
          'tab-title',
        ],
        'role' => [
          'tab',
        ],
        'data-toggle' => [
          'tab',
        ],
        'aria-selected' => [
          'true',
        ],
        'tabindex' => [
          '0',
        ],
      ],
      'fragment' => 'title',
    ];
    $title_url->setOptions($title_link_options);

    $fulltext_url = Url::fromUri("internal:/");
    $fulltext_link_options = [
      'attributes' => [
        'id' => [
          'tab-fulltext',
        ],
        'role' => [
          'tab',
        ],
        'data-toggle' => [
          'tab',
        ],
        'aria-selected' => [
          'false',
        ],
      ],
      'fragment' => 'fulltext',
    ];
    $fulltext_url->setOptions($fulltext_link_options);

    $about_url = Url::fromUri("internal:/");
    $about_link_options = [
      'attributes' => [
        'id' => [
          'tab-about',
        ],
        'role' => [
          'tab',
        ],
        'data-toggle' => [
          'tab',
        ],
        'aria-selected' => [
          'false',
        ],

      ],
      'fragment' => 'about',
    ];
    $about_url->setOptions($about_link_options);

    $blurb = "<p>Newspapers &commat; UNB Libraries provides researchers with
        unified access to UNB Libraries&apos; current and historical newspaper collections in all formats, from New
        Brunswick and across the world. Search and discover
        <a href=\"/print-titles\">print</a>, microform, and selected digital newspaper
        titles (including New Brunswick Historical Newspapers Online collection) available from UNB Libraries.</p>
        <p>Fulltext Search is available for titles included in
        <a href=\"/digital-titles\">New Brunswick Historical Newspapers Online</a>.
        For more worldwide digital newspaper content, consult
        <a href=\"https://lib.unb.ca/eresources/index.php?sub=journals&browseNewsColl=y\" class=\"external\">
        UNB Libraries licensed electronic Newspaper collections</a>.</p>";

    $form['blurb'] = [
      '#type' => 'markup',
      '#markup' => "<div class=\"hidden-xs message well\">" . $blurb . "</div>",
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
      '#markup' => '<li class="tab' . $title_tab_class . '">' . Link::fromTextAndUrl($this->t('Title Search'), $title_url)
        ->toString() . '</li>',
    ];
    $form['nav-tabs']['fulltext'] = [
      '#markup' => '<li class="tab' . $fulltext_tab_class . '">' . Link::fromTextAndUrl($this->t('Fulltext Search'), $fulltext_url)
        ->toString() . '</li>',
    ];
    $form['nav-tabs']['about'] = [
      '#markup' => '<li class="tab' . $about_tab_class . '">' . Link::fromTextAndUrl($this->t('About'), $about_url)
        ->toString() . '</li>',
    ];

    $form['tab-content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tab-content',
          'search-form',
        ],
      ],
    ];
    $form['tab-content']['title'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tab-pane',
          $title_pane_class,
        ],
        'id' => [
          'title',
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

    $form['tab-content']['title']['submit_title'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search/Browse Titles'),
      '#field_prefix' => '<span class="input-group-btn">',
    ];
    $form['tab-content']['fulltext'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'clearfix',
          'tab-pane',
          $fulltext_pane_class,
        ],
        'id' => [
          'fulltext',
        ],
        'aria-labelledby' => [
          'tab-fulltext',
        ],
      ],
    ];
    $form['tab-content']['fulltext']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'clearfix',
        ],
      ],
    ];
    $form['tab-content']['fulltext']['wrapper']['input_fulltext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search the fulltext of digitized newspapers'),
      '#description' => $this->t('Search for keywords within the fulltext content of newspapers.'),
    ];
    $form['tab-content']['fulltext']['wrapper']['submit_fulltext'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search FullText'),
      '#field_prefix' => '<span class="input-group-btn">',
      '#field_suffix' => '</span>',
      '#attributes' => [
        'class' => [
          'btn-danger',
        ],
      ],
    ];
    $form['tab-content']['fulltext']['notes'] = [
      '#type' => 'markup',
      '#markup' => '<p class="alert alert-info fade in">
        <span class="glyphicon glyphicon glyphicon-ok-circle"></span>
        <b>Note:</b>
        Only a limited number of newspapers have been digitized to date and new content will be added on a
        continuing basis. See a current listing of <a href="/digital-titles">digitally available titles and coverage
        dates</a>.</p>',
    ];

    $form['tab-content']['about'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'clearfix',
          'tab-pane',
          $about_pane_class,
        ],
        'id' => [
          'about',
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
      $form_state->setRedirectUrl(
        Url::fromUri("internal:/search?query=$query")
      );
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
