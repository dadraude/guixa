<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\E2eSeeder;

test('every user seeded by DatabaseSeeder has a member profile', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->doesntHave('member')->count())->toBe(0)
        ->and(User::count())->toBeGreaterThan(1);
});

test('every user seeded by E2eSeeder has a member profile', function () {
    $this->seed(E2eSeeder::class);

    expect(User::query()->doesntHave('member')->count())->toBe(0)
        ->and(User::count())->toBeGreaterThan(1);
});
