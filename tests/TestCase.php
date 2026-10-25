<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    private static ?string $baseRedisPrefix = null;

    protected function setUp(): void
    {
        $this->isolateRedisPerParallelWorker();

        parent::setUp();

        // Redis outlives each test, unlike the array drivers: without this, cache entries and
        // rate-limiter hits leak between tests.
        $this->deleteAppRedisKeys();
    }

    /**
     * Parallel workers share the Redis DBs, so each gets "<prefix>-p<token>:" (guixa-test-p3:)
     * before the app boots; otherwise one worker's cleanup would delete another's keys.
     */
    private function isolateRedisPerParallelWorker(): void
    {
        $token = $_SERVER['TEST_TOKEN'] ?? false;

        if ($token === false) {
            return;
        }

        self::$baseRedisPrefix ??= (string) ($_SERVER['REDIS_PREFIX'] ?? $_ENV['REDIS_PREFIX'] ?? getenv('REDIS_PREFIX'));
        $workerPrefix = rtrim(self::$baseRedisPrefix, ':')."-p{$token}:";

        putenv("REDIS_PREFIX={$workerPrefix}");
        $_ENV['REDIS_PREFIX'] = $workerPrefix;
        $_SERVER['REDIS_PREFIX'] = $workerPrefix;
    }

    /**
     * Deletes only the keys under this app's Redis prefix, never FLUSHDB: the local Redis server
     * is shared with other apps and with other worktrees (each sets its own REDIS_PREFIX). The
     * prefix must end with ":" so "guixa-test:*" never matches a worktree's "guixa-test-dra196:".
     */
    protected function deleteAppRedisKeys(): void
    {
        foreach (['default', 'cache'] as $connection) {
            $client = Redis::connection($connection)->client();
            $prefix = $client->getOption(\Redis::OPT_PREFIX);

            if (! is_string($prefix) || $prefix === '') {
                throw new \LogicException("Redis connection [{$connection}] has no prefix; refusing to delete keys.");
            }

            if (! str_ends_with($prefix, ':')) {
                throw new \LogicException("Redis connection [{$connection}] prefix [{$prefix}] must end with \":\"; refusing to delete keys.");
            }

            // SCAN matches raw key names, and DEL would prefix them again, so work unprefixed.
            $client->setOption(\Redis::OPT_PREFIX, '');

            try {
                $cursor = null;

                while (($keys = $client->scan($cursor, $prefix.'*', 1000)) !== false) {
                    if ($keys !== []) {
                        $client->del($keys);
                    }
                }
            } finally {
                $client->setOption(\Redis::OPT_PREFIX, $prefix);
            }
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
