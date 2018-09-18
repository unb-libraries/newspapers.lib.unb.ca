<?php

namespace Drupal\newspapers_core;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Class Breadcrumbs.
 *
 * @package Drupal\modulename
 */
class NewspaperTitleBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'entity.node.canonical'
      && $route_match->getParameter('node') instanceof NodeInterface
      && $route_match->getParameter('node')->bundle() == 'publication';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(["url"]);
    $breadcrumb->addCacheTags(["node:{$node->nid->value}"]);

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

    return $breadcrumb;
  }

}
