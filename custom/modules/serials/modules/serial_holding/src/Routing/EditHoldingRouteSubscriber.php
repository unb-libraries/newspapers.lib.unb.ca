<?php

namespace Drupal\serial_holding\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class EditHoldingRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.serial_holding.edit_form')) {
      $route->setOption('_admin_route', FALSE);
    }
  }

}
