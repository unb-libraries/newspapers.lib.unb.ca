<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Breadcrumb builder for digital serials.
 */
class DigitalSerialIssueBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $applies = [
      'entity.digital_serial_issue.edit_form',
      'entity.digital_serial_issue.delete_form',
      'digital_serial_issue.title_issues',
      'digital_serial_issue.title_add_issue',
      'digital_serial_issue.title_edit_issue',
      'digital_serial_issue.title_view_issue',
    ];
    return in_array($route, $applies);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();

    // Load title object.
    $title_eid = $route_match->getParameter('digital_serial_title');

    if ($route == 'entity.digital_serial_issue.edit_form' || $route == 'entity.digital_serial_issue.delete_form') {
      $issue = $route_match->getParameter('digital_serial_issue');
      $title = $issue->getParentTitle();
      $parent_title = $title->getParentPublication();
    }
    elseif (!is_object($title_eid)) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_title');
      $title = $storage->load($title_eid);
      $parent_title = $title->get('parent_title')->entity;
    }
    else {
      $title = $title_eid;
      $parent_title = $title->get('parent_title')->entity;
    }

    // Set upstream breadcrumbs.
    $breadcrumb = new Breadcrumb();

    $breadcrumb->addLink(
      Link::fromTextAndUrl(
        $this->t('UNB Libraries'),
        Url::fromUri('https://lib.unb.ca')
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Newspapers'),
        '<front>'
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        $parent_title->label(),
        'entity.node.canonical',
        ['node' => $parent_title->id()]
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        t('Digital Issues'),
        'entity.digital_serial_title.canonical',
        ['digital_serial_title' => $title->id()]
      )
    );

    if ($route == 'digital_serial_issue.title_issues') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Manage Issues'),
          '<nolink>'
        )
      );
    }

    if ($route == 'digital_serial_issue.title_edit_issue') {
      // Load title object.
      $issue_eid = $route_match->getParameter('digital_serial_issue');

      if (!is_object($issue_eid)) {
        $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
        $issue = $storage->load($issue_eid);
      }
      else {
        $issue = $issue_eid;
      }

      $breadcrumb->addLink(
        Link::createFromRoute(
          $issue->getDisplayTitle(),
          'digital_serial_issue.title_view_issue',
          ['digital_serial_title' => $title->id(), 'digital_serial_issue' => $issue->id()]
        )
      );
    }

    if ($route == 'digital_serial_issue.title_add_issue') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Issues'),
          'digital_serial_issue.title_issues',
          ['digital_serial_title' => $title->id()]
        )
      );
      $breadcrumb->addLink(Link::createFromRoute('Add Issue', '<nolink>'));
    }

    if ($route == 'entity.digital_serial_issue.edit_form' || $route == 'entity.digital_serial_issue.delete_form') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $issue->getDisplayTitle(),
          'digital_serial_issue.title_view_issue',
          [
            'digital_serial_title' => $title->id(),
            'digital_serial_issue' => $issue->id(),
          ]
        )
      );
    }

    if ($route == 'entity.digital_serial_issue.edit_form') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Edit'),
          '<nolink>'
        )
      );
    }

    if ($route == 'entity.digital_serial_issue.delete_form') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Delete'),
          '<nolink>'
        )
      );
    }

    $breadcrumb->addCacheTags(["digital_serial_title:{$title->id()}"]);
    $breadcrumb->addCacheContexts(['url']);

    return $breadcrumb;
  }

}
