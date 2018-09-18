<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Breadcrumb builder for digital pages.
 */
class DigitalSerialPageBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $applies = [
      'digital_serial_page.manage_pages',
      'digital_serial_page.issue_add_page',
      'digital_serial_page.issue_view_page',
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
    if (!is_object($title_eid)) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_title');
      $title = $storage->load($title_eid);
      $parent_title = $title->get('parent_title')->entity;
    }
    else {
      $title = $title_eid;
      $parent_title = $title->get('parent_title')->entity;
    }

    // Load issue object.
    $issue_eid = $route_match->getParameter('digital_serial_issue');
    if (!is_object($issue_eid)) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_issue');
      $issue = $storage->load($issue_eid);
    }
    else {
      $issue = $issue_eid;
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

    if ($route == 'digital_serial_page.manage_pages') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Manage Pages'),
          '<nolink>'
        )
      );
    }

    if ($route == 'digital_serial_page.issue_add_page') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Add Page'),
          '<nolink>'
        )
      );
    }

    // Control Caching.
    $breadcrumb->addCacheTags(["digital_serial_title:{$title->id()}"]);
    $breadcrumb->addCacheContexts(['url']);

    return $breadcrumb;
  }

}
