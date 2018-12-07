<?php

namespace Drupal\newspapers_core;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Breadcrumb builder for search views.
 *
 * @package Drupal\newspapers_core
 */
class SearchViewsBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return in_array(
      $route_match->getRouteName(),
      [
        'view.digital_page_lister.page_search',
        'view.search.search_page',
        'view.digital_title_listing.page_listing',
        'view.print_title_listing.page_listing',
        'view.digital_page_lister.page_issues',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteName();
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(["url"]);

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

    $digital_serial_title_id = $route_match->getParameter('arg_0');
    if ($route == 'view.digital_page_lister.page_issues' && is_numeric($digital_serial_title_id)) {
      $storage = \Drupal::entityTypeManager()->getStorage('digital_serial_title');
      $title = $storage->load($digital_serial_title_id);
      if (!empty($title)) {
        $parent_title = $title->get('parent_title')->entity;
        $breadcrumb->addLink(
          Link::createFromRoute(
            $parent_title->getTitle(),
            'entity.node.canonical',
            ['node' => $parent_title->id()]
          )
        );
      }
    }

    return $breadcrumb;
  }

}
