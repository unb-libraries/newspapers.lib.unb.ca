<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;

/**
 * Breadcrumb builder for digital serials.
 */
class DigitalSerialTitleBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $applies = [
      'entity.digital_serial_title.canonical',
      'digital_serial_title.title_issues',
    ];
    return in_array($route, $applies);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $parameters = explode('.', $route);

    // Load title object.
    $title_eid = $route_match->getParameter('digital_serial_title');
    if (!is_object($title_eid)) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_title');
      $title = $storage->load($title_eid);
    }
    else {
      $title = $title_eid;
    }

    // Set breadcrumb.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute('Newspapers', '<front>'));

    // Add title to breadcrumb.
    if ($route == 'entity.digital_serial_title.canonical') {
      $breadcrumb->addLink(Link::createFromRoute($title->label(), '<nolink>'));
    }
    else {
      $breadcrumb->addLink(Link::createFromRoute($title->label(), '<front>'));
    }

    if ($route == 'digital_serial_title.title_issues') {
      $breadcrumb->addLink(Link::createFromRoute('Issues', '<nolink>'));
    }

    // Control Caching.
    $breadcrumb->addCacheContexts(['url']);
    $breadcrumb->addCacheTags(["digital_serial_title:{$title->id()}"]);

    return $breadcrumb;
  }

}
