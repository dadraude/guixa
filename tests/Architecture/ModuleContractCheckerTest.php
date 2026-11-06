<?php

use Tests\Support\Architecture\ModuleContract;

it('rejects a foreign Application helper that is not an exported interface', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $source = <<<'PHP'
<?php
namespace Guixa\Catalog\Infrastructure\Ordering;
use Guixa\Ordering\Application\CycleSettlement;
final class Adapter { public function __construct(private CycleSettlement $helper) {} }
PHP;

    expect(implode("\n", $checker->violations('src/Catalog/Infrastructure/Ordering/Adapter.php', $source)))
        ->toContain('Ordering\\Application\\CycleSettlement is not exported to Catalog');
});

it('allows declared integration exports with aliases and grouped imports', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $source = <<<'SOURCE'
<?php
namespace Guixa\Ordering\Infrastructure\Catalog;
use Guixa\Catalog\Application\{GetOrderableProduct as Find, OrderableProductData};
final class Adapter {
    public function __construct(private Find $find) {}
    public function map(\Guixa\Catalog\Application\OrderableProductData $data): OrderableProductData { return $data; }
}
SOURCE;
    expect($checker->violations('src/Ordering/Infrastructure/Catalog/Adapter.php', $source))->toBe([]);
});

it('rejects undeclared module dependencies', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Wallet/Infrastructure/Adapter.php', '<?php new \Guixa\Catalog\Application\GetProducer;')))
        ->toContain('Wallet may not depend on Catalog');
});

it('restricts an export to its declared callers', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Adapter.php', '<?php new \Guixa\Wallet\Application\ReadHouseholdBalance;')))
        ->toContain('ReadHouseholdBalance is not exported to Ordering');
});

it('keeps Sales discovery limited to its delivered read exports', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');

    expect($checker->violations(
        'src/Sales/Infrastructure/Catalog/Adapter.php',
        '<?php new \Guixa\Catalog\Application\ListStockOfferProducts;',
    ))->toBe([])
        ->and(implode("\n", $checker->violations(
            'src/Sales/Infrastructure/Catalog/Adapter.php',
            '<?php new \Guixa\Catalog\Application\CreateStockProduct;',
        )))->toContain('CreateStockProduct is not exported to Sales')
        ->and(implode("\n", $checker->violations(
            'src/Ordering/Infrastructure/Catalog/Adapter.php',
            '<?php new \Guixa\Catalog\Application\ListStockOfferProducts;',
        )))->toContain('ListStockOfferProducts is not exported to Ordering');
});

it('admits only the T55 basket integration exports and rejects unrelated or future writers', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');

    foreach ([
        ['src/Sales/Infrastructure/Catalog/Adapter.php', '\\Guixa\\Catalog\\Application\\LockStockProduct'],
        ['src/Sales/Infrastructure/Stock/Adapter.php', '\\Guixa\\Stock\\Application\\SellBasketStock'],
        ['src/Sales/Infrastructure/Wallet/Adapter.php', '\\Guixa\\Wallet\\Application\\ChargeForBasket'],
        ['src/Catalog/Infrastructure/Sales/Adapter.php', '\\Guixa\\Sales\\Application\\HasProductHistory'],
    ] as [$path, $symbol]) {
        expect($checker->violations($path, "<?php new {$symbol};"))->toBe([]);
    }

    foreach ([
        ['src/Sales/Infrastructure/Catalog/Adapter.php', '\\Guixa\\Catalog\\Application\\CreateStockProduct', 'CreateStockProduct is not exported to Sales'],
        ['src/Sales/Infrastructure/Stock/Adapter.php', '\\Guixa\\Stock\\Application\\RecordStockEntry', 'RecordStockEntry is not exported to Sales'],
        ['src/Sales/Infrastructure/Wallet/Adapter.php', '\\Guixa\\Wallet\\Application\\RecordTopUp', 'RecordTopUp is not exported to Sales'],
        ['src/Ordering/Infrastructure/Wallet/Adapter.php', '\\Guixa\\Wallet\\Application\\ChargeForBasket', 'ChargeForBasket is not exported to Ordering'],
        ['src/Ordering/Infrastructure/Stock/Adapter.php', '\\Guixa\\Stock\\Application\\SellBasketStock', 'SellBasketStock is not exported to Ordering'],
        ['src/Catalog/Infrastructure/Sales/Adapter.php', '\\Guixa\\Sales\\Application\\ValidateBasket', 'ValidateBasket is not exported to Catalog'],
    ] as [$path, $symbol, $message]) {
        expect(implode("\n", $checker->violations($path, "<?php new {$symbol};")))->toContain($message);
    }

    $source = "<?php use Illuminate\\Support\\Facades\\DB; DB::table('baskets')->get();";
    expect(implode("\n", $checker->violations('src/Catalog/Infrastructure/Sales/Adapter.php', $source)))
        ->toContain('baskets belongs to Sales');
});

it('does not allow foreign exports directly in Application', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Ordering/Application/Adapter.php', '<?php new \Guixa\Catalog\Application\GetOrderableProduct;')))
        ->toContain('foreign module references belong in Infrastructure');
});

it('keeps Shared infrastructure private', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Adapter.php', '<?php new \Guixa\Shared\Infrastructure\SystemClock;')))
        ->toContain('SystemClock is not exported to Ordering');
});

it('requires new modules to be declared', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect($checker->violations('src/Surprise/Application/Command.php', '<?php'))->toContain('src/Surprise/Application/Command.php: undeclared module Surprise');
});

it('rejects a foreign table even without a foreign class import', function (string $statement) {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $source = '<?php use Illuminate\Support\Facades\DB as Database; '.$statement;
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/Probe.php', $source)))
        ->toContain('products belongs to Catalog');
})->with([
    'builder' => "Database::table('products')->get();",
    'connection' => "Database::connection()->table('products')->delete();",
    'nullsafe connection' => "Database::connection()?->table('products')->get();",
    'join' => "Database::table('orders')->leftJoin('products as p', 'p.id', '=', 'orders.id')->get();",
    'model mapping' => "class Probe extends \\Illuminate\\Database\\Eloquent\\Model { protected \$table = 'products'; }",
    'inferred model mapping' => 'class Product extends \\Illuminate\\Database\\Eloquent\\Model {}',
]);

/** The checker still supports a reviewed dynamic-table helper; the shipped manifest declares none, so these examples declare their own. */
function manifestWithDynamicHelper(): array
{
    $manifest = require dirname(__DIR__, 2).'/architecture/modules.php';
    $manifest['dynamic_tables'] = [
        'src/Ordering/Infrastructure/Persistence/EloquentOrderRepository.php' => [
            'rowsOf' => ['parameter' => 'table', 'reason' => 'Checker example only: a private helper with literal table names at every call site.'],
        ],
    ];

    return $manifest;
}

it('keeps the retired Ordering stock, return and adjustment tables and their dynamic helper forbidden', function () {
    $manifest = require dirname(__DIR__, 2).'/architecture/modules.php';
    $checker = new ModuleContract($manifest);
    $source = <<<'SOURCE'
<?php
namespace Guixa\Ordering\Infrastructure\Persistence;
use Illuminate\Support\Facades\DB;
final class EloquentOrderRepository {
    private function rowsOf(string $table, string $orderId): array { return DB::table($table)->get()->all(); }
    public function load(string $id): array { return $this->rowsOf('order_lines', $id); }
}
SOURCE;

    expect($manifest['dynamic_tables'])->toBe([])
        ->and($manifest['tables'])->not->toHaveKeys(['order_stock_lines', 'order_return_lines', 'order_pickup_adjustments', 'stock_products'])
        ->and(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/EloquentOrderRepository.php', $source)))->toContain('dynamic table access requires a reviewed declaration');
    foreach (['order_stock_lines', 'order_return_lines', 'order_pickup_adjustments'] as $table) {
        expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/Probe.php', "<?php use Illuminate\\Support\\Facades\\DB; DB::table('{$table}')->get();")))
            ->toContain("table {$table} has no declared owner");
    }
});

it('allows owned tables only from Infrastructure', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $source = "<?php use Illuminate\\Support\\Facades\\DB; DB::table('orders')->update(['pickup_status' => 'completed']);";
    expect($checker->violations('src/Ordering/Infrastructure/Persistence/Probe.php', $source))->toBe([])
        ->and(implode("\n", $checker->violations('src/Ordering/Application/Probe.php', $source)))->toContain('orders belongs to Ordering');
});

it('limits the actor resolver exception to named tables and read operations', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $path = 'src/Shared/Infrastructure/DatabaseActorResolver.php';
    $source = '<?php use Illuminate\Support\Facades\DB; ';
    expect($checker->violations($path, $source."DB::table('users')->first();"))->toBe([])
        ->and(implode("\n", $checker->violations($path, $source."DB::table('users')->delete();")))->toContain('users belongs to Membership')
        ->and(implode("\n", $checker->violations($path, $source."DB::table('products')->get();")))->toContain('products belongs to Catalog')
        ->and(implode("\n", $checker->violations('src/Shared/Infrastructure/OtherResolver.php', $source."DB::table('users')->get();")))->toContain('users belongs to Membership');
});

it('fails closed for unknown tables and dynamic table names', function (string $statement, string $message) {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/Probe.php', '<?php use Illuminate\Support\Facades\DB; '.$statement)))
        ->toContain($message);
})->with([
    ['DB::table($table)->get();', 'dynamic table access requires a reviewed declaration'],
    ["DB::table('undeclared')->get();", 'table undeclared has no declared owner'],
    ["DB::table(as: 'orders', table: 'products')->get();", 'products belongs to Catalog'],
    ["DB::select('select * from products');", 'raw SQL requires a reviewed declaration'],
    ["DB::connection()->select('select * from products');", 'raw SQL requires a reviewed declaration'],
    ["DB::connection()?->select('select * from products');", 'raw SQL requires a reviewed declaration'],
    ["DB::table('orders')->selectRaw('(select count(*) from products) as count')->get();", 'raw SQL requires a reviewed declaration'],
]);

it('permits only the exact declared table-free advisory lock SQL', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $path = 'src/Membership/Infrastructure/Persistence/DatabaseHouseholdRepository.php';
    $source = '<?php use Illuminate\Support\Facades\DB; ';
    expect($checker->violations($path, $source."DB::select('select pg_advisory_xact_lock(191983, 1)');"))->toBe([])
        ->and(implode("\n", $checker->violations($path, $source."DB::select('select * from users');")))->toContain('raw SQL requires a reviewed declaration');
});

it('checks every literal caller of the existing private dynamic-table helper', function (string $argument, string $message) {
    $checker = new ModuleContract(manifestWithDynamicHelper());
    $source = <<<'SOURCE'
<?php
namespace Guixa\Ordering\Infrastructure\Persistence;
use Illuminate\Support\Facades\DB;
final class EloquentOrderRepository {
    private function rowsOf(string $table, string $orderId): array { return DB::table($table)->get()->all(); }
    public function load(string $id, string $table): array { return $this->rowsOf(ARGUMENT, $id); }
}
SOURCE;
    $issues = implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/EloquentOrderRepository.php', str_replace('ARGUMENT', $argument, $source)));
    if ($message === '') {
        expect($issues)->toBe('');
    } else {
        expect($issues)->toContain($message);
    }
})->with([
    ["'order_lines'", ''],
    ["'products'", 'products belongs to Catalog'],
    ['$table', 'dynamic table access requires a reviewed declaration'],
]);

it('checks container class strings as well as native type references', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Catalog/Infrastructure/Probe.php', "<?php app('Guixa\\\\Ordering\\\\Application\\\\CycleSettlement');")))
        ->toContain('CycleSettlement is not exported to Catalog');
});

it('does not treat a deferred query builder as a read-only exception', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $source = '<?php use Illuminate\Support\Facades\DB; $query = DB::table("users"); $query->delete();';
    expect(implode("\n", $checker->violations('src/Shared/Infrastructure/DatabaseActorResolver.php', $source)))
        ->toContain('users belongs to Membership');
});

it('checks Eloquent table overrides and SQL on an assigned connection', function (string $source, string $message) {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/Probe.php', '<?php '.$source)))
        ->toContain($message);
})->with([
    ["\\Guixa\\Ordering\\Infrastructure\\Persistence\\CycleModel::from('products')->get();", 'products belongs to Catalog'],
    ["\$connection->select('select * from products');", 'raw SQL requires a reviewed declaration'],
    ["\$pdo->prepare('delete from products');", 'raw SQL requires a reviewed declaration'],
]);

it('does not let a module bypass table ownership through the host authentication model', function () {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Catalog/Infrastructure/Probe.php', '<?php \App\Models\User::query()->get();')))
        ->toContain('users belongs to Membership');
});

it('rejects direct business model access from the application host', function (string $path) {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations($path, '<?php \Guixa\Ordering\Infrastructure\Persistence\CycleModel::query()->get();')))
        ->toContain('cycles belongs to Ordering');
})->with(['routes/web.php', 'app/Console/Commands/Probe.php']);

it('checks static and nullsafe callers of the declared dynamic-table helper', function (string $call) {
    $checker = new ModuleContract(manifestWithDynamicHelper());
    $source = <<<'SOURCE'
<?php
namespace Guixa\Ordering\Infrastructure\Persistence;
use Illuminate\Support\Facades\DB;
final class EloquentOrderRepository {
    private function rowsOf(string $table, string $orderId): array { return DB::table($table)->get()->all(); }
    public function load(string $id): array { $this->rowsOf('order_lines', $id); return CALL; }
}
SOURCE;
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/EloquentOrderRepository.php', str_replace('CALL', $call, $source))))
        ->toContain('products belongs to Catalog');
})->with(['self::rowsOf(\'products\', $id)', '$this?->rowsOf(\'products\', $id)']);

it('rejects callbacks and unrelated reads in the read-only exception', function (string $statement) {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    expect(implode("\n", $checker->violations('src/Shared/Infrastructure/DatabaseActorResolver.php', '<?php use Illuminate\Support\Facades\DB; '.$statement)))
        ->toContain('users belongs to Membership');
})->with([
    'tap writes' => "DB::table('users')->tap(fn (\$query) => \$query->delete())->get();",
    'where callback writes' => "DB::table('users')->where(function (\$query) { \$query->delete(); })->get();",
    'unrelated outer read' => "\$service->get(DB::table('users'));",
    'deferred builder in closure' => "\$service->get(fn () => DB::table('users'));",
]);

it('requires the declared dynamic-table parameter to remain unchanged', function (string $mutation) {
    $checker = new ModuleContract(manifestWithDynamicHelper());
    $source = <<<'SOURCE'
<?php
namespace Guixa\Ordering\Infrastructure\Persistence;
use Illuminate\Support\Facades\DB;
final class EloquentOrderRepository {
    private function rowsOf(string $table, string $orderId): array { MUTATION return DB::table($table)->get()->all(); }
    public function load(string $id): array { return $this->rowsOf('order_lines', $id); }
}
SOURCE;
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/EloquentOrderRepository.php', str_replace('MUTATION', $mutation, $source))))
        ->toContain('dynamic table access requires a reviewed declaration');
})->with([
    'assignment' => "\$table = 'products';",
    'reference alias' => "\$alias =& \$table; \$alias = 'products';",
    'reference argument' => 'rewrite($table);',
    'reference parameter' => '$table .= "_archive";',
]);

it('rejects undeclared SQL executions on assigned connections', function (string $statement) {
    $checker = new ModuleContract(require dirname(__DIR__, 2).'/architecture/modules.php');
    $source = '<?php use Illuminate\Support\Facades\DB; $connection = DB::connection(); $sql = "delete from products"; '.$statement;
    expect(implode("\n", $checker->violations('src/Ordering/Infrastructure/Persistence/Probe.php', $source)))
        ->toContain('raw SQL requires a reviewed declaration');
})->with([
    'statement' => '$connection->statement($sql);',
    'select' => '$connection->select($sql);',
    'PDO prepare' => '$pdo->prepare($sql);',
    'scalar' => 'DB::scalar("select count(*) from products");',
    'write connection' => 'DB::selectFromWriteConnection("select * from products");',
]);
