<?php

namespace Drupal\digital_serial_issue\Controller;

use Drupal\digital_serial_issue\Entity\SerialIssue;
use Drupal\digital_serial_title\Entity\SerialTitle;

/**
 * DigitalSerialIssueViewTitleController object.
 */
class DigitalSerialIssueViewTitleController {

  /**
   * Get title of serial issue.
   */
  public function getTitle(SerialTitle $digital_serial_title, SerialIssue $digital_serial_issue) {
    return $digital_serial_issue->getFormattedIssueDisplayTitle();
  }

}
