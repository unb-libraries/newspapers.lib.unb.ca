<?php
/*
Include local dev-only settings here.
*/

include_once DRUPAL_ROOT . '/sites/example.settings.local.php';

$conf['chq_redis_cache_enabled'] = TRUE;
if (isset($conf['chq_redis_cache_enabled']) && $conf['chq_redis_cache_enabled']) {
  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = 'drupal-redis';
  $settings['redis.connection']['port'] = '6379';
  // Note that unlike memcached, redis persists cache items to disk so we can
  // actually store cache_class_cache_form in the default cache.
  $conf['cache_class_cache'] = 'Redis_Cache';
}
