<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('exposes only the household pickup redirect: nothing completes, charges or abandons a pickup (T63)', function () {
    $pickupRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), '/pickups'))
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()->all();

    expect($pickupRoutes)->toBe([
        'GET|HEAD manage/cycles/{cycle}/pickups/{household}',
    ]);

    $actions = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->getActionName())->implode("\n");
    expect($actions)->not->toContain('CompletePickup')->not->toContain('NotCollected')->not->toContain('ChargeForPickup');
});

it('refuses every write on a pickup path for a Manager, as a missing route (T63)', function (string $method, string $suffix) {
    $this->actingAs(accountWith(Role::Manager));
    $cycle = (string) Str::uuid7();
    $household = (string) Str::uuid7();

    $this->call($method, "/manage/cycles/{$cycle}/pickups/{$household}{$suffix}")->assertStatus(in_array($method, ['POST', 'PUT', 'DELETE'], true) && $suffix === '' ? 405 : 404);
})->with([
    ['POST', ''],
    ['PUT', ''],
    ['DELETE', ''],
    ['POST', '/completion'],
    ['POST', '/not-collected'],
    ['POST', '/stock'],
    ['POST', '/returns'],
    ['PUT', '/delivered'],
]);
