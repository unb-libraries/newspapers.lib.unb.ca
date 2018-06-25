<?php

namespace Drupal\digital_serial_page\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

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
  public function buildForm(array $form, FormStateInterface $form_state, $digital_serial_title = NULL, $digital_serial_issue = NULL, $digital_serial_page = NULL) {
    $form = [];

    $form['page_view']['back_link'] = [
      '#markup' => Link::fromTextAndUrl(
        'Back to Issue',
        Url::fromUri("internal:/serials/{$digital_serial_title->id()}/issues/{$digital_serial_issue->id()}")
      )->toString(),
    ];

    $title = [
      '#type' => 'processed_text',
      '#text' => $this->t('High Resolution Image'),
      '#format' => 'full_html',
      '#langcode' => 'en',
    ];
    $form['page_view']['title'] = $title;
    $form['page_view']['title']['#prefix'] = '<h2 class="viewer-title">';
    $form['page_view']['title']['#suffix'] = '</h2>';

    $form['page_view']['zoom'] = [
      '#markup' => '<div id="seadragon-viewer"></div>',
    ];

    $form['#attached'] = [
      'library' => [
        'digital_serial_page/openseadragon',
        'digital_serial_page/openseadragon_viewer',
      ],
      'drupalSettings' => [
        'digital_serial_page' => [
          'dzi_filepath' => "/sites/default/files/dzi/1.dzi",
        ],
      ],
    ];

    $title = [
      '#type' => 'processed_text',
      '#text' => $this->t('Text On Page'),
      '#format' => 'full_html',
      '#langcode' => 'en',
    ];
    $form['page_text']['title'] = $title;
    $form['page_text']['title']['#prefix'] = '<h2 class="viewer-title">';
    $form['page_text']['title']['#suffix'] = '</h2>';

    $form['page_text']['text'] = [
      '#markup' => "<blockquote>Bacon ipsum dolor amet meatloaf shoulder boudin sirloin meatball pork chop flank picanha corned beef t-bone tenderloin beef ribs strip steak swine drumstick. Porchetta burgdoggen prosciutto spare ribs flank pancetta. Cupim sausage shank capicola. Meatball rump alcatra ribeye drumstick pastrami flank jowl bacon landjaeger cow cupim.\n\nShank ham brisket venison pastrami sirloin frankfurter corned beef. Biltong buffalo tail, chicken cow short loin chuck pastrami. Strip steak tri-tip pork loin fatback tail ball tip bacon kielbasa capicola ribeye pork chop hamburger flank burgdoggen andouille. Cow frankfurter corned beef short ribs jerky brisket t-bone, drumstick pork loin short loin turducken swine tongue. Turkey alcatra sirloin cow burgdoggen. Prosciutto shankle bresaola shank, venison leberkas strip steak brisket spare ribs picanha meatloaf. Shoulder pork belly tri-tip doner cupim short ribs prosciutto jerky leberkas meatloaf ground round ribeye.</blockquote>",
    ];

    return $form;
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
