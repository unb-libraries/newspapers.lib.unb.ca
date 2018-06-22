<?php

namespace Drupal\digital_serial_issue;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
      'digital_serial_issue.title_issues',
      'digital_serial_issue.title_add_issue',
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

    // Set upstreams breadcrumbs.
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(
      Link::createFromRoute(
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

    if ($route == 'digital_serial_issue.title_issues') {
      $breadcrumb->addLink(
        Link::createFromRoute(
          $this->t('Issues'),
          '<nolink>'
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

    $breadcrumb->addCacheTags(["digital_serial_title:{$title->id()}"]);
    $breadcrumb->addCacheContexts(['url']);

    return $breadcrumb;
  }

}
