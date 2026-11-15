<?php

use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Facades\Route;

it('exposes only append and read operations on the Wallet and stock ledgers (R28, R32)', function (string $repository) {
    $mutators = collect((new ReflectionClass($repository))->getMethods())
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->filter(fn (string $name) => preg_match('/^(update|delete|remove|edit|save|replace|set|truncate)/i', $name) === 1)
        ->values()->all();

    expect($mutators)->toBe([]);
})->with([
    'wallet movements' => MovementRepository::class,
    'stock movements' => StockMovementRepository::class,
]);

it('offers no HTTP route that edits or deletes a ledger movement, receipt or correction (R28, R32)', function () {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => preg_match('#(top-ups|corrections|entries|adjustments|movements|receipts)#', $route->uri()) === 1)
        ->flatMap(fn ($route) => collect($route->methods())->reject(fn (string $method) => in_array($method, ['GET', 'HEAD', 'POST'], true))
            ->map(fn (string $method) => "{$method} {$route->uri()}"))
        ->values()->all();

    expect($writes)->toBe([]);
});
