<?php

namespace Drupal\newspapers_core\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * HomePageTitleController object.
 */
class HomePageTitleController {

  use StringTranslationTrait;

  /**
   * Get the title of the homepage.
   *
   * @return string
   *   The title of the homepage.
   */
  public function getTitle() {
    return $this->t('New Brunswick Historical Newspapers Project');
  }

}
