<?php

use Guixa\Catalog\Application\CreateStockProduct;
use Guixa\Catalog\Application\GetStockProduct;
use Guixa\Catalog\Application\ListStockProducts;
use Guixa\Catalog\Application\LockStockProduct;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\AppendStockAdjustment;
use Guixa\Stock\Application\CorrectBasketStock;
use Guixa\Stock\Application\ListStock;
use Guixa\Stock\Application\ListStockMovements;
use Guixa\Stock\Application\RecordStockAdjustment;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\SellBasketStock;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Infrastructure\Catalog\CatalogStockProductRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Catalog\CatalogWorld;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\FakeAccountNames;
use Tests\Support\InMemory\FakeStockCyclePickupDates;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryStockMovementRepository;
use Tests\Support\InMemory\InMemoryStockProductRepository;

beforeEach(function () {
    $this->products = new InMemoryStockProductRepository;
    $this->movements = new InMemoryStockMovementRepository;
    $this->clock = new FrozenClock('2026-10-20 09:00:00');
    $this->names = new FakeAccountNames;
    $this->pickups = new FakeStockCyclePickupDates;
    $ledger = new StockLedger($this->movements);
    $transactions = new ImmediateTransactionRunner;
    $append = new AppendStockAdjustment($this->movements, $ledger, $this->clock);

    $this->entry = new RecordStockEntry($this->products, $this->movements, $ledger, $transactions, $this->clock);
    $this->adjust = new RecordStockAdjustment($this->products, $append, $transactions);
    $this->sell = new SellBasketStock($this->products, $this->movements, $ledger, $transactions, $this->clock);
    $this->correct = new CorrectBasketStock($this->products, $this->movements, $ledger, $transactions, $this->clock);
    $this->history = new ListStockMovements($this->products, $this->movements, $this->names, $this->pickups);

    $this->manager = Actor::manager('manager-account');
    $this->finance = new Actor('finance-account', [Role::Finance]);
    $this->beekeeper = Actor::producer('beekeeper-account', 'beekeeper');
    $this->names->know('manager-account', 'Maria Manager');
    $this->names->know('buyer-account', 'Ana Buyer');
    $this->pickups->know('cycle-1', '2026-10-24');

    $this->honeyId = $this->products->create('Honey', 'jar', Money::ofCents(650), false, null, 'beekeeper')->id;

    // S93: entry 10, adjustment -1, sale 3, correction +1, each one minute apart.
    $this->at = fn (string $time) => $this->clock->travelTo("2026-10-20 {$time}");
    ($this->at)('09:00');
    ($this->entry)($this->manager, $this->honeyId, '10', 'bought from X');
    ($this->at)('09:01');
    ($this->adjust)($this->manager, $this->honeyId, '-1', 'broken jar', 'cycle-1');
    ($this->at)('09:02');
    ($this->sell)(Actor::manager('buyer-account'), $this->honeyId, '3', 'basket-A');
    ($this->at)('09:03');
    ($this->correct)(Actor::manager('buyer-account'), $this->honeyId, '1', 'wrong count', 'basket-A', 'correction-1');
});

it('lists the movements newest first with the available stock right after each (S93, R28, R28a)', function () {
    $page = ($this->history)($this->manager, $this->honeyId);

    expect($page->total)->toBe(4)
        ->and(array_map(fn ($line) => $line->type, $page->lines))->toBe(['correction', 'sale', 'adjustment', 'entry'])
        ->and(array_map(fn ($line) => $line->balanceAfter, $page->lines))->toBe(['7.000', '6.000', '9.000', '10.000'])
        ->and(array_map(fn ($line) => $line->quantity, $page->lines))->toBe(['1.000', '-3.000', '-1.000', '10.000'])
        ->and(array_map(fn ($line) => $line->note, $page->lines))->toBe(['wrong count', null, 'broken jar', 'bought from X']);
});

it('shows the newest balance equal to the available stock even when occurrence times tie (R28)', function () {
    ($this->at)('09:03');
    ($this->entry)($this->manager, $this->honeyId, '5', null);
    ($this->entry)($this->manager, $this->honeyId, '2', null);

    $page = ($this->history)($this->manager, $this->honeyId);
    $available = collect((new ListStock($this->products, $this->movements))($this->manager))->firstWhere('id', $this->honeyId)->available;

    expect($page->lines[0]->balanceAfter)->toBe($available)->and($available)->toBe('14.000');
});

it('derives a correction from an adjustment that carries a correction id (S93)', function () {
    $lines = ($this->history)($this->manager, $this->honeyId)->lines;

    expect($lines[0]->type)->toBe('correction')->and($lines[2]->type)->toBe('adjustment');
});

it('names each author, deactivated accounts included, and shows the cycle pickup date (S93, R28a)', function () {
    $lines = ($this->history)($this->manager, $this->honeyId)->lines;

    expect($lines[0]->authorName)->toBe('Ana Buyer')
        ->and($lines[1]->authorName)->toBe('Ana Buyer')
        ->and($lines[2]->authorName)->toBe('Maria Manager')
        ->and($lines[2]->cycleId)->toBe('cycle-1')
        ->and($lines[2]->pickupDate)->toBe('2026-10-24')
        ->and($lines[3]->cycleId)->toBeNull()
        ->and($lines[3]->pickupDate)->toBeNull()
        ->and($lines[3]->occurredAt)->toBe('2026-10-20T09:00:00+02:00');
});

it('links sale and correction lines to their basket for Manager and Finance (S93, R28a)', function (Actor $actor) {
    $lines = ($this->history)($actor, $this->honeyId)->lines;

    expect(array_map(fn ($line) => $line->basketId, $lines))->toBe(['basket-A', 'basket-A', null, null]);
})->with(fn () => [
    'manager' => [Actor::manager('manager-account')],
    'finance' => [new Actor('finance-account', [Role::Finance])],
]);

it('lets the producer see the same lines of their own product without any basket (S93, R25b, R28a)', function () {
    $lines = ($this->history)($this->beekeeper, $this->honeyId)->lines;

    expect(array_map(fn ($line) => $line->type, $lines))->toBe(['correction', 'sale', 'adjustment', 'entry'])
        ->and(array_map(fn ($line) => $line->balanceAfter, $lines))->toBe(['7.000', '6.000', '9.000', '10.000'])
        ->and(array_map(fn ($line) => $line->basketId, $lines))->toBe([null, null, null, null]);
});

it('keeps the basket for an account that is both producer and manager (R36a)', function () {
    $both = new Actor('beekeeper-account', [Role::Producer, Role::Manager], producerId: 'beekeeper');

    expect(($this->history)($both, $this->honeyId)->lines[0]->basketId)->toBe('basket-A');
});

it('refuses another producer, a member and a producer without the product (S93, R25b)', function (Actor $actor) {
    expect(fn () => ($this->history)($actor, $this->honeyId))->toThrow(AuthorizationException::class);
})->with([
    'oil producer' => [Actor::producer('oil-account', 'oil')],
    'member' => [Actor::memberOf('member-account', 'household')],
    'producer role without producer id' => [new Actor('lost-account', [Role::Producer])],
]);

it('keeps the history after the product is deactivated, for all three roles (S93, R28a)', function () {
    $this->products->deactivate($this->honeyId);

    foreach ([$this->manager, $this->finance, $this->beekeeper] as $actor) {
        expect(($this->history)($actor, $this->honeyId)->total)->toBe(4);
    }
});

it('pages the history 50 lines at a time, newest first (S93)', function () {
    for ($i = 0; $i < 56; $i++) {
        ($this->entry)($this->manager, $this->honeyId, '1', "entry {$i}");
    }

    $first = ($this->history)($this->manager, $this->honeyId);
    $second = ($this->history)($this->manager, $this->honeyId, 2);
    $beyond = ($this->history)($this->manager, $this->honeyId, 3);

    expect($first->total)->toBe(60)->and($first->lines)->toHaveCount(50)
        ->and($first->lines[0]->balanceAfter)->toBe('63.000')
        ->and($second->lines)->toHaveCount(10)
        ->and($second->lines[9]->note)->toBe('bought from X')
        ->and($second->lines[9]->balanceAfter)->toBe('10.000')
        ->and($beyond->lines)->toBe([])->and($beyond->total)->toBe(60);
});

it('treats a page below one as the first page', function () {
    expect(($this->history)($this->manager, $this->honeyId, 0)->lines)->toHaveCount(4);
});

it('rejects an unknown product and a weekly product as not stock (S93, R28a)', function () {
    $catalog = new CatalogWorld;
    $producerId = ($catalog->createProducer)($catalog->manager, 'Beekeeper', 3, '20:00');
    $weeklyId = ($catalog->createProduct)($catalog->producerActor($producerId), $producerId, 'Honey', 'jar', 500, false, null);
    $stockId = (new CreateStockProduct($catalog->products, $catalog->producers))($catalog->manager, $producerId, 'Honey', 'jar', 650, false, null);
    $port = new CatalogStockProductRepository(
        new GetStockProduct($catalog->products),
        new ListStockProducts($catalog->products),
        new LockStockProduct($catalog->products),
    );
    $history = new ListStockMovements($port, $this->movements, $this->names, $this->pickups);

    expect(fn () => $history($this->manager, 'missing'))->toThrow(StockProductNotFound::class)
        ->and(fn () => $history($catalog->producerActor($producerId), $weeklyId))->toThrow(StockProductNotFound::class)
        ->and($history($catalog->producerActor($producerId), $stockId)->total)->toBe(0);
});

it('carries the product header with the available stock, even for a deactivated product (R28, R28a)', function () {
    $this->products->deactivate($this->honeyId);

    $page = ($this->history)($this->finance, $this->honeyId);

    expect($page->productName)->toBe('Honey')
        ->and($page->unit)->toBe('jar')
        ->and($page->available)->toBe('7.000');
});

it('returns no lines for a page past the last one, even for an absurd page number (S93)', function () {
    $huge = ($this->history)($this->manager, $this->honeyId, PHP_INT_MAX);

    expect($huge->lines)->toBe([])->and($huge->total)->toBe(4)
        ->and(($this->history)($this->manager, $this->honeyId, 2)->lines)->toBe([]);
});
