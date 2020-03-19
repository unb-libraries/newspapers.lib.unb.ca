<?php

/**
 * @file
 * Redis configuration settings.
 */

if (isset($conf['chq_redis_cache_enabled']) && $conf['chq_redis_cache_enabled']) {
  $conf['cache_class_cache'] = 'Redis_Cache';
  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = 'drupal-redis-lib-unb-ca';
  $settings['redis.connection']['port'] = '6379';
}
