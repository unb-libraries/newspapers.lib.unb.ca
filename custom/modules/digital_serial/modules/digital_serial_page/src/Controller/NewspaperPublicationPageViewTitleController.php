<?php

namespace Drupal\digital_serial_page\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\digital_serial_issue\Entity\SerialIssue;
use Drupal\digital_serial_page\Entity\SerialPage;
use Drupal\digital_serial_title\Entity\SerialTitle;

/**
 * DigitalSerialIssueViewTitleController object.
 */
class NewspaperPublicationPageViewTitleController {

  use StringTranslationTrait;

  /**
   * Get title of the page view.
   */
  public function getTitle(SerialTitle $digital_serial_title, SerialIssue $digital_serial_issue, SerialPage $digital_serial_page) {
    $publication = $digital_serial_title->get('parent_title')->entity;
    $pub_title = $publication->getTitle();
    $issue_title = $digital_serial_issue->getDisplayTitle();
    $display_page_no = $digital_serial_page->getPageNo();

    $title = $this->t(
      '@pub_title: @issue_title - Page @page_no',
      [
        '@pub_title' => $pub_title ,
        '@issue_title' => $issue_title,
        '@page_no' => $display_page_no,
      ]
    );

    return $title;
  }

}
