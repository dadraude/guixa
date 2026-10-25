<?php

use Illuminate\Cache\RedisStore;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

it('runs the cache, the session and the queue on Redis', function () {
    expect(config('cache.default'))->toBe('redis')
        ->and(config('session.driver'))->toBe('redis')
        ->and(config('queue.default'))->toBe('redis')
        ->and(Cache::getStore())->toBeInstanceOf(RedisStore::class)
        ->and(Queue::connection())->toBeInstanceOf(RedisQueue::class);
});

it('stores cache entries in Redis', function () {
    Cache::put('redis-backends-probe', 'stored', 60);

    expect(Cache::get('redis-backends-probe'))->toBe('stored');
});

// Runs after the test above, so it proves entries do not leak from one test to the next.
it('starts every test without the cache entries of the previous one', function () {
    expect(Cache::get('redis-backends-probe'))->toBeNull();
});

it('deletes only the Redis keys of this app between tests', function () {
    $client = Redis::connection()->client();
    $appPrefix = $client->getOption(\Redis::OPT_PREFIX);
    $client->setOption(\Redis::OPT_PREFIX, '');
    // A worktree's prefix (guixa-test-dra196:) starts like the main checkout's (guixa-test:).
    $siblingKey = rtrim($appPrefix, ':').'-dra999:probe';
    $client->set('other-app:probe', 'kept');
    $client->set($siblingKey, 'kept');
    $client->set($appPrefix.'probe', 'deleted');
    $client->setOption(\Redis::OPT_PREFIX, $appPrefix);

    $this->deleteAppRedisKeys();

    $client->setOption(\Redis::OPT_PREFIX, '');
    expect($client->get('other-app:probe'))->toBe('kept')
        ->and($client->get($siblingKey))->toBe('kept')
        ->and($client->exists($appPrefix.'probe'))->toBe(0);
    $client->del('other-app:probe', $siblingKey);
    $client->setOption(\Redis::OPT_PREFIX, $appPrefix);
});

it('refuses to delete keys under a prefix that does not end with a colon', function () {
    $client = Redis::connection()->client();
    $appPrefix = $client->getOption(\Redis::OPT_PREFIX);
    $client->setOption(\Redis::OPT_PREFIX, 'guixa-test');

    try {
        expect(fn () => $this->deleteAppRedisKeys())
            ->toThrow(LogicException::class, 'Redis connection [default] prefix [guixa-test] must end with ":"');
    } finally {
        $client->setOption(\Redis::OPT_PREFIX, $appPrefix);
    }
});

it('gives each parallel test worker its own Redis prefix', function () {
    $token = $_SERVER['TEST_TOKEN'] ?? false;
    $prefix = Redis::connection()->client()->getOption(\Redis::OPT_PREFIX);

    expect($prefix)->toEndWith($token === false ? ':' : "-p{$token}:");
});
