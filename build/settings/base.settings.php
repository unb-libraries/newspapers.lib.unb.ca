<?php

/**
 * @file
 * Include global settings overrides here.
 */

// Redis.
$settings['cache_prefix']['default'] = 'DRUPAL_SITE_ID_';
$conf['chq_redis_cache_enabled'] = TRUE;
require_once dirname(__FILE__) . "/settings.redis.inc";

// Newrelic.
if (extension_loaded('newrelic')) {
  require_once dirname(__FILE__) . "/settings.newrelic.inc";
}

$settings['config_sync_directory'] = 'DRUPAL_CONFIGURATION_DIR';

// Address ranges with no facet limit (facet_bot_blocker_exempt_cidrs, from
// patches/facet_bot_blocker_exempt_cidrs.patch). The ranges are not kept in this public
// repository: 54_set_facet_bot_blocker_exempt_cidrs.sh writes the comma-separated
// FACET_BOT_BLOCKER_EXEMPT_CIDRS environment variable over the token below at startup.
// Left unreplaced, or empty, nobody is exempt.
$facet_bot_blocker_exempt_cidrs = array_values(array_filter(
  array_map('trim', explode(',', 'FACET_BOT_BLOCKER_EXEMPT_CIDRS_VALUE')),
  fn($cidr) => (bool) preg_match('~^[0-9A-Fa-f.:]+(/[0-9]{1,3})?$~', $cidr)
));
if ($facet_bot_blocker_exempt_cidrs) {
  $config['facet_bot_blocker.settings']['facet_bot_blocker_exempt_cidrs'] = $facet_bot_blocker_exempt_cidrs;
}
