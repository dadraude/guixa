<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

const MATRIX_ID = '00000000-0000-7000-8000-00000000abcd';

/** @return array<string, list<string>> every role-gated route as "METHOD uri" => the roles its middleware allows */
function roleGatedRoutes(): array
{
    $gated = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var LaravelRoute $route */
        $roles = collect($route->gatherMiddleware())->first(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'role:'));
        if ($roles === null) {
            continue;
        }
        $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');
        $gated["{$method} {$route->uri()}"] = explode(',', substr($roles, 5));
    }

    return $gated;
}

function matrixAccount(?Role $role): User
{
    $account = $role === null ? accountWith() : accountWith($role);
    if ($role === Role::Producer) {
        // A producer role only works for an account linked to a producer record.
        $producer = ProducerModel::query()->create(['name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $account->forceFill(['producer_id' => $producer->id])->save();
    }

    return $account;
}

it('denies every role-gated route to guests and to each account holding none of its roles (R2, R19a, R25b, R33, R36a, R36c, S44, S56, S71, S84)', function () {
    $routes = roleGatedRoutes();
    expect(count($routes))->toBeGreaterThan(90);
    $accounts = [
        'member' => matrixAccount(Role::Member),
        'producer' => matrixAccount(Role::Producer),
        'manager' => matrixAccount(Role::Manager),
        'finance' => matrixAccount(Role::Finance),
        'none' => matrixAccount(null),
    ];
    $leaks = [];

    foreach ($routes as $signature => $allowed) {
        [$method, $uri] = explode(' ', $signature, 2);
        $path = '/'.ltrim(preg_replace('/\{[^}]+\}/', MATRIX_ID, $uri), '/');

        auth()->logout();
        $guest = test()->call($method, $path);
        if (! $guest->isRedirect(route('login')) && $guest->status() !== 401) {
            $leaks[] = "guest {$signature} -> {$guest->status()}";
        }

        foreach ($accounts as $name => $account) {
            if (in_array($name, $allowed, true)) {
                continue;
            }
            $status = test()->actingAs($account)->call($method, $path)->status();
            if ($status !== 403) {
                $leaks[] = "{$name} {$signature} -> {$status}";
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('never answers 403 at the role middleware to an account holding one of the allowed roles (R19a, R36c)', function () {
    $accounts = [
        'member' => matrixAccount(Role::Member),
        'producer' => matrixAccount(Role::Producer),
        'manager' => matrixAccount(Role::Manager),
        'finance' => matrixAccount(Role::Finance),
    ];
    $refused = [];

    foreach (roleGatedRoutes() as $signature => $allowed) {
        [$method, $uri] = explode(' ', $signature, 2);
        $path = '/'.ltrim(preg_replace('/\{[^}]+\}/', MATRIX_ID, $uri), '/');
        foreach ($allowed as $role) {
            $status = test()->actingAs($accounts[$role])->call($method, $path)->status();
            if ($status === 403) {
                $refused[] = "{$role} {$signature}";
            }
        }
    }

    expect($refused)->toBe([]);
});
