<?php

namespace Drupal\serial_holding;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Breadcrumb builder for manage holdings.
 */
class SerialManageHoldingsBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $applies = [
      'serial_holding.manage_serial_holdings',
      'serial_holding.add_holding',
    ];
    return in_array($route, $applies);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $nid = $route_match->getParameter('node');
    $node = Node::load($nid);
    $node_title = $node->getTitle();

    // Set breadcrumb.
    $breadcrumb = new Breadcrumb();

    // Set links.
    $links = [
      Link::fromTextAndUrl(
        $this->t('UNB Libraries'),
        Url::fromUri('https://lib.unb.ca')
      ),
      Link::createFromRoute(
        $this->t('Newspapers'),
        '<front>'
      ),
      Link::createFromRoute(
        $node_title,
        'entity.node.canonical',
        ['node' => $nid]
      ),
    ];
    $breadcrumb->setLinks($links);

    return $breadcrumb;
  }

}
