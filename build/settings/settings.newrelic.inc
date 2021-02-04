<?php

/**
 * @file
 * Newrelic configuration settings.
 */

if (
  isset($_SERVER['REQUEST_URI']) &&
  (
    strpos($_SERVER['REQUEST_URI'], '/health') !== FALSE ||
    strpos($_SERVER['REQUEST_URI'], '/admin/') !== FALSE
  )
) {
  newrelic_disable_autorum();
}
