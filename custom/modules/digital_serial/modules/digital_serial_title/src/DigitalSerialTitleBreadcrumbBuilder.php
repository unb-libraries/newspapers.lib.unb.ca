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
    $breadcrumb = new Breadcrumb();

    $breadcrumb->addLink(Link::createFromRoute('Newspapers', '<nolink>'));
    $breadcrumb->addLink(Link::createFromRoute('Telegraph Journal', '<front>'));

    if ($route == 'digital_serial_title.title_issues') {
      $breadcrumb->addLink(Link::createFromRoute('Issues', '<front>'));
    }

    $breadcrumb->addCacheContexts(['url']);
    // $breadcrumb->addCacheTags(["node:{$node->nid->value}"]);

    return $breadcrumb;
  }

}
