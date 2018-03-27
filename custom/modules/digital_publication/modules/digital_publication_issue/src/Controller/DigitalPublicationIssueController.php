<?php

namespace Drupal\digital_publication_issue\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface;

/**
 * Class DigitalPublicationIssueController.
 *
 *  Returns responses for Digital publication issue routes.
 */
class DigitalPublicationIssueController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Digital publication issue  revision.
   *
   * @param int $digital_publication_issue_revision
   *   The Digital publication issue  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($digital_publication_issue_revision) {
    $digital_publication_issue = $this->entityManager()->getStorage('digital_publication_issue')->loadRevision($digital_publication_issue_revision);
    $view_builder = $this->entityManager()->getViewBuilder('digital_publication_issue');

    return $view_builder->view($digital_publication_issue);
  }

  /**
   * Page title callback for a Digital publication issue  revision.
   *
   * @param int $digital_publication_issue_revision
   *   The Digital publication issue  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($digital_publication_issue_revision) {
    $digital_publication_issue = $this->entityManager()->getStorage('digital_publication_issue')->loadRevision($digital_publication_issue_revision);
    return $this->t('Revision of %title from %date', ['%title' => $digital_publication_issue->label(), '%date' => format_date($digital_publication_issue->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Digital publication issue .
   *
   * @param \Drupal\digital_publication_issue\Entity\DigitalPublicationIssueInterface $digital_publication_issue
   *   A Digital publication issue  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(DigitalPublicationIssueInterface $digital_publication_issue) {
    $account = $this->currentUser();
    $langcode = $digital_publication_issue->language()->getId();
    $langname = $digital_publication_issue->language()->getName();
    $languages = $digital_publication_issue->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $digital_publication_issue_storage = $this->entityManager()->getStorage('digital_publication_issue');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $digital_publication_issue->label()]) : $this->t('Revisions for %title', ['%title' => $digital_publication_issue->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all digital publication issue revisions") || $account->hasPermission('administer digital publication issue entities')));
    $delete_permission = (($account->hasPermission("delete all digital publication issue revisions") || $account->hasPermission('administer digital publication issue entities')));

    $rows = [];

    $vids = $digital_publication_issue_storage->revisionIds($digital_publication_issue);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\digital_publication_issue\DigitalPublicationIssueInterface $revision */
      $revision = $digital_publication_issue_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $digital_publication_issue->getRevisionId()) {
          $link = $this->l($date, new Url('entity.digital_publication_issue.revision', ['digital_publication_issue' => $digital_publication_issue->id(), 'digital_publication_issue_revision' => $vid]));
        }
        else {
          $link = $digital_publication_issue->link($date);
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
              'url' => $has_translations ?
              Url::fromRoute('entity.digital_publication_issue.translation_revert', ['digital_publication_issue' => $digital_publication_issue->id(), 'digital_publication_issue_revision' => $vid, 'langcode' => $langcode]) :
              Url::fromRoute('entity.digital_publication_issue.revision_revert', ['digital_publication_issue' => $digital_publication_issue->id(), 'digital_publication_issue_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.digital_publication_issue.revision_delete', ['digital_publication_issue' => $digital_publication_issue->id(), 'digital_publication_issue_revision' => $vid]),
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

    $build['digital_publication_issue_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
