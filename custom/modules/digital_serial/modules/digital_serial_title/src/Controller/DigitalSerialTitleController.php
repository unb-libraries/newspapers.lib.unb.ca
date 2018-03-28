<?php

namespace Drupal\digital_serial_title\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface;

/**
 * Class DigitalSerialTitleController.
 *
 *  Returns responses for Digital serial title routes.
 */
class DigitalSerialTitleController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Digital serial title  revision.
   *
   * @param int $digital_serial_title_revision
   *   The Digital serial title  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($digital_serial_title_revision) {
    $digital_serial_title = $this->entityManager()->getStorage('digital_serial_title')->loadRevision($digital_serial_title_revision);
    $view_builder = $this->entityManager()->getViewBuilder('digital_serial_title');

    return $view_builder->view($digital_serial_title);
  }

  /**
   * Page title callback for a Digital serial title  revision.
   *
   * @param int $digital_serial_title_revision
   *   The Digital serial title  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($digital_serial_title_revision) {
    $digital_serial_title = $this->entityManager()->getStorage('digital_serial_title')->loadRevision($digital_serial_title_revision);
    return $this->t('Revision of %title from %date', ['%title' => $digital_serial_title->label(), '%date' => format_date($digital_serial_title->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Digital serial title .
   *
   * @param \Drupal\digital_serial_title\Entity\DigitalSerialTitleInterface $digital_serial_title
   *   A Digital serial title  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(DigitalSerialTitleInterface $digital_serial_title) {
    $account = $this->currentUser();
    $langcode = $digital_serial_title->language()->getId();
    $langname = $digital_serial_title->language()->getName();
    $languages = $digital_serial_title->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $digital_serial_title_storage = $this->entityManager()->getStorage('digital_serial_title');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $digital_serial_title->label()]) : $this->t('Revisions for %title', ['%title' => $digital_serial_title->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all digital serial title revisions") || $account->hasPermission('administer digital serial title entities')));
    $delete_permission = (($account->hasPermission("delete all digital serial title revisions") || $account->hasPermission('administer digital serial title entities')));

    $rows = [];

    $vids = $digital_serial_title_storage->revisionIds($digital_serial_title);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\digital_serial_title\DigitalSerialTitleInterface $revision */
      $revision = $digital_serial_title_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $digital_serial_title->getRevisionId()) {
          $link = $this->l($date, new Url('entity.digital_serial_title.revision', ['digital_serial_title' => $digital_serial_title->id(), 'digital_serial_title_revision' => $vid]));
        }
        else {
          $link = $digital_serial_title->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => Url::fromRoute('entity.digital_serial_title.revision_revert', ['digital_serial_title' => $digital_serial_title->id(), 'digital_serial_title_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.digital_serial_title.revision_delete', ['digital_serial_title' => $digital_serial_title->id(), 'digital_serial_title_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['digital_serial_title_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
