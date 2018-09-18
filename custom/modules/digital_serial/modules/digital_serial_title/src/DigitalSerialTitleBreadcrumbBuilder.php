<?php

namespace Drupal\digital_serial_title;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Breadcrumb builder for digital serials.
 */
class DigitalSerialTitleBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $applies = [
      'entity.digital_serial_title.canonical',
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
        $parent_title->label(),
        'entity.node.canonical',
        ['node' => $parent_title->id()]
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        'Digital Issues',
        '<nolink>'
      )
    );

    return $breadcrumb;
  }

}
