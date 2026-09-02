<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use Yii;

/**
 * Prevents duplicate concurrent Amazon calls for the same hash/lang pair.
 */
class ConcurrencyLock
{
    public function acquire(string $key, int $ttlSeconds = 60): bool
    {
        $cacheKey = 'tt-lock:' . $key;
        if (Yii::$app->cache->exists($cacheKey)) {
            return false;
        }
        return (bool)Yii::$app->cache->set($cacheKey, 1, $ttlSeconds);
    }

    public function release(string $key): void
    {
        Yii::$app->cache->delete('tt-lock:' . $key);
    }

    public function waitFor(string $key, callable $lookup, int $maxMs = 5000)
    {
        $started = (int)(microtime(true) * 1000);
        while (((int)(microtime(true) * 1000) - $started) < $maxMs) {
            $val = $lookup();
            if ($val !== null && $val !== '') {
                return $val;
            }
            usleep(100000);
        }
        return null;
    }
}
