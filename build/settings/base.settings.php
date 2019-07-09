<?php

/**
 * @file
 * Include global settings overrides here.
 */

// Redis.
$settings['cache_prefix']['default'] = 'newspapers_';
$conf['chq_redis_cache_enabled'] = TRUE;
require_once dirname(__FILE__) . "/settings.redis.inc";
