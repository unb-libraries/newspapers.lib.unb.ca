<?php

namespace Drupal\newspapers_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\block_content\Entity\BlockContent;
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

    $form['fulltext_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FullText:'),
    ];

    $form['submit_fulltext_input'] = [
      '#type' => 'submit',
      '#value' => t('Search FullText'),
    ];

    $form['title_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title:'),
    ];

    $form['submit_title_input'] = [
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
    $op = (string) $form_state->getValue('op');
    $fulltext_input = (string) $values['fulltext_input'];
    $title_input = (string) $values['title_input'];

    if ($op === 'Search FullText' && empty($fulltext_input) && empty($title_input)) {
      $form_state->setErrorByName('fulltext_input', $this->t('Please provide a search term'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $op = (string) $form_state->getValue('op');
    $title_input = (string) $values['title_input'];
    $fulltext_input = (string) $values['fulltext_input'];

    if ($op === 'Search FullText' && empty($fulltext_input) && !empty($title_input)) {
      $query = $this->getQueryFromValue($title_input);
      $form_state->setRedirectUrl(
        Url::fromUri("internal:/search?query=$query")
      );
    }
    elseif ($op === 'Search FullText' && !empty($fulltext_input)) {
      $query = $this->getQueryFromValue($fulltext_input);
      $form_state->setRedirectUrl(
        Url::fromUri("internal:/page-search?fulltext=$query")
      );
    }
    elseif ($op === 'Search/Browse Titles') {
      $query = $this->getQueryFromValue($title_input);
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
