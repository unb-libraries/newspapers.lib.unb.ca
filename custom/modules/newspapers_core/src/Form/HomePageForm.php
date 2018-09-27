<?php

namespace Drupal\newspapers_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
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
    $form = [];
    $fulltext_text = t('Fulltext');
    $title_text = t('Title');

    $title_url = Url::fromUri("internal:/");
    $fulltext_url = Url::fromUri("internal:/");

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
        'tabindex' => [
          '-1',
        ],
      ],
      'fragment' => 'fulltext',
    ];
    $fulltext_url->setOptions($fulltext_link_options);

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

    $form['nav-tabs']['fulltext'] = [
      '#markup' => '<li class="tab active">' . Link::fromTextAndUrl(t('Title Search'), $title_url)
        ->toString() . '</li>',
    ];

    $form['nav-tabs']['title'] = [
      '#markup' => '<li class="tab">' . Link::fromTextAndUrl(t('Fulltext Search'), $fulltext_url)
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

    $form['tab-content']['fulltext'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tab-pane',
        ],
        'id' => [
          'fulltext',
        ],
        'aria-labelledby' => [
          'tab-fulltext',
        ],
      ],
    ];
    $form['tab-content']['fulltext']['input_fulltext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search FullText for terms containing:'),
    ];
    $form['tab-content']['fulltext']['submit_fulltext'] = [
      '#type' => 'submit',
      '#value' => t('Search FullText'),
      '#attributes' => [
        'class' => [
          'btn-danger',
        ],
      ],
    ];

    $form['tab-content']['title'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'tab-pane',
          'active',
          'in',
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
      '#title' => $this->t('Search Titles for terms containing:'),
    ];

    $form['tab-content']['title']['submit_title'] = [
      '#type' => 'submit',
      '#value' => t('Search/Browse Titles'),
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
