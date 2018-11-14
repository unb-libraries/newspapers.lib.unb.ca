<?php

namespace Drupal\serial_holding;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Breadcrumb builder for serial holdings.
 */
class SerialHoldingBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $applies = [
      'entity.serial_holding.edit_form',
      'entity.serial_holding.delete_form',
    ];
    return in_array($route, $applies);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();

    // Load title object.
    $title = $route_match->getParameter('serial_holding')->getParentTitle();

    // Set breadcrumb.
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
        $title->label(),
        'entity.node.canonical',
        ['node' => $title->id()]
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Holdings'),
        'serial_holding.manage_serial_holdings',
        [
          'node' => $title->id(),
        ]
      )
    );

    if ($route == 'entity.serial_holding.delete_form') {
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
