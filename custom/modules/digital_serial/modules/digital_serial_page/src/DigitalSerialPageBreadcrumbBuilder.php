<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
      'entity.digital_serial_page.edit_form',
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
    }
    else {
      $title = $title_eid;
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

    // Set breadcrumb.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute(
      $this->t('Newspapers'),
      '<front>')
    );
    $breadcrumb->addLink(
      Link::createFromRoute(
        $title->label(),
        'entity.digital_serial_title.canonical',
        ['digital_serial_title' => $title->id()]
      )
    );
    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Issues'),
        'digital_serial_issue.title_issues',
        ['digital_serial_title' => $title->id()]
      )
    );
    $breadcrumb->addLink(
      Link::createFromRoute(
        $issue->getDisplayTitle(),
        'digital_serial_issue.title_view_issue',
        ['digital_serial_title' => $title->id(), 'digital_serial_issue' => $issue->id()]
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

    if ($route == 'digital_serial_page.issue_view_page') {
      // Load page object.
      $page_eid = $route_match->getParameter('digital_serial_page');
      if (!is_object($page_eid)) {
        $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_page');
        $page = $storage->load($page_eid);
      }
      else {
        $page = $page_eid;
      }

      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t(
            'Page @page_no',
            [
              '@page_no' => $page->getPageNo(),
            ]
          ),
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
