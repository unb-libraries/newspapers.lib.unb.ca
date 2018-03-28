<?php

namespace Drupal\digital_serial_issue\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface;

/**
 * Class DigitalSerialIssueController.
 *
 *  Returns responses for Digital serial issue routes.
 */
class DigitalSerialIssueController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Digital serial issue  revision.
   *
   * @param int $digital_serial_issue_revision
   *   The Digital serial issue  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($digital_serial_issue_revision) {
    $digital_serial_issue = $this->entityManager()->getStorage('digital_serial_issue')->loadRevision($digital_serial_issue_revision);
    $view_builder = $this->entityManager()->getViewBuilder('digital_serial_issue');

    return $view_builder->view($digital_serial_issue);
  }

  /**
   * Page title callback for a Digital serial issue  revision.
   *
   * @param int $digital_serial_issue_revision
   *   The Digital serial issue  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($digital_serial_issue_revision) {
    $digital_serial_issue = $this->entityManager()->getStorage('digital_serial_issue')->loadRevision($digital_serial_issue_revision);
    return $this->t('Revision of %title from %date', ['%title' => $digital_serial_issue->label(), '%date' => format_date($digital_serial_issue->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Digital serial issue .
   *
   * @param \Drupal\digital_serial_issue\Entity\DigitalSerialIssueInterface $digital_serial_issue
   *   A Digital serial issue  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(DigitalSerialIssueInterface $digital_serial_issue) {
    $account = $this->currentUser();
    $langcode = $digital_serial_issue->language()->getId();
    $langname = $digital_serial_issue->language()->getName();
    $languages = $digital_serial_issue->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $digital_serial_issue_storage = $this->entityManager()->getStorage('digital_serial_issue');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $digital_serial_issue->label()]) : $this->t('Revisions for %title', ['%title' => $digital_serial_issue->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all digital serial issue revisions") || $account->hasPermission('administer digital serial issue entities')));
    $delete_permission = (($account->hasPermission("delete all digital serial issue revisions") || $account->hasPermission('administer digital serial issue entities')));

    $rows = [];

    $vids = $digital_serial_issue_storage->revisionIds($digital_serial_issue);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\digital_serial_issue\DigitalSerialIssueInterface $revision */
      $revision = $digital_serial_issue_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $digital_serial_issue->getRevisionId()) {
          $link = $this->l($date, new Url('entity.digital_serial_issue.revision', ['digital_serial_issue' => $digital_serial_issue->id(), 'digital_serial_issue_revision' => $vid]));
        }
        else {
          $link = $digital_serial_issue->link($date);
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
              'url' => Url::fromRoute('entity.digital_serial_issue.revision_revert', ['digital_serial_issue' => $digital_serial_issue->id(), 'digital_serial_issue_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.digital_serial_issue.revision_delete', ['digital_serial_issue' => $digital_serial_issue->id(), 'digital_serial_issue_revision' => $vid]),
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

    $build['digital_serial_issue_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
