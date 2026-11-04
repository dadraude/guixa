<?php

use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\OpenBasketData;
use Guixa\Sales\Application\ReadOpenHouseholdBaskets;
use Guixa\Sales\Application\StockSeller;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketAdjustmentLine;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\NoWeeklyBasketSource;

beforeEach(function () {
    $this->manager = Actor::manager('manager');
    $clock = new FrozenClock('2026-11-07 10:00:00');
    $transactions = new ImmediateTransactionRunner;
    $this->baskets = new InMemoryBasketRepository;
    $this->when = new DateTimeImmutable('2026-11-02');
    $products = new class implements StockProducts
    {
        public function lock(string $productId): ?StockProductSnapshot
        {
            return $productId === 'honey' ? new StockProductSnapshot('honey', 'Honey', 'jar', 700, false, '1', true) : null;
        }
    };
    $stock = new class implements StockSeller
    {
        public function sell(Actor $actor, string $productId, string $quantity, string $basketId): void {}
    };
    $wallet = new class implements WalletLedger
    {
        public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void {}

        public function balance(Actor $actor, string $householdId): int
        {
            return 0;
        }

        public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void {}
    };
    $pickupDates = new class implements CyclePickupDates
    {
        public function forCycles(array $cycleIds): array
        {
            return array_intersect_key(['cycle-1' => '2026-11-07', 'cycle-2' => '2026-10-31'], array_flip($cycleIds));
        }
    };
    $this->validate = new ValidateBasket($this->baskets, $products, $stock, $wallet, new NoWeeklyBasketSource, $transactions, $clock);
    $this->collect = new MarkBasketCollected($this->baskets, $transactions, $clock);
    $this->open = new ReadOpenHouseholdBaskets($this->baskets, $pickupDates);

    /** Saves a basket of the household in the given state; weekly ones hold a weekly line, the others a returned jar. */
    $this->basket = function (string $id, string $household, BasketState $state, bool $weekly, string $created = '2026-11-02', ?string $cycle = null): Basket {
        $when = new DateTimeImmutable($created);
        $line = $weekly
            ? new BasketLine("{$id}-line", $id, 'bread', 'Bread', 'loaf', 300, false, null, Quantity::fromString('1'), $when, kind: BasketLineKind::Weekly)
            : new BasketLine("{$id}-line", $id, null, 'Jar', 'jar', 50, false, null, Quantity::fromString('1'), $when, kind: BasketLineKind::Return, returnItemId: 'jar');
        $draft = new Basket($id, $household, 'Household', BasketState::Draft, null, $when, [$line],
            kind: $weekly ? BasketKind::Weekly : BasketKind::Stock, referenceCycleId: $cycle);
        $basket = match ($state) {
            BasketState::Draft => $draft,
            BasketState::Validated => $draft->validated('manager', $when),
            BasketState::Collected => $draft->validated('manager', $when)->collected('manager', $when),
            BasketState::Cancelled => $draft->cancelled('manager', $when),
            BasketState::NothingToCollect => new Basket($id, $household, 'Household', $state, null, $when, [$line], kind: BasketKind::Weekly, referenceCycleId: $cycle),
        };
        $this->baskets->save($basket);

        return $basket;
    };
    $this->summary = fn (string $household) => array_map(fn (OpenBasketData $b) => [$b->basketId, $b->status, $b->date], ($this->open)($household));
});

it('lists only draft and pending-collection baskets, a weekly one by its pickup date and the others by creation date (R35a)', function () {
    ($this->basket)('weekly-draft', 'h', BasketState::Draft, true, cycle: 'cycle-1');
    ($this->basket)('weekly-validated', 'h', BasketState::Validated, true, cycle: 'cycle-2');
    ($this->basket)('weekly-unknown-cycle', 'h', BasketState::Draft, true, '2026-11-04', 'cycle-9');
    ($this->basket)('stock-draft', 'h', BasketState::Draft, false, '2026-11-03');
    ($this->basket)('stock-validated', 'h', BasketState::Validated, false, '2026-11-01');
    ($this->basket)('weekly-collected', 'h', BasketState::Collected, true, cycle: 'cycle-1');
    ($this->basket)('weekly-nothing', 'h', BasketState::NothingToCollect, true, cycle: 'cycle-1');
    ($this->basket)('stock-cancelled', 'h', BasketState::Cancelled, false);
    ($this->basket)('stock-collected', 'h', BasketState::Collected, false);
    ($this->basket)('other-household', 'k', BasketState::Draft, false);

    expect(($this->summary)('h'))->toBe([
        ['weekly-validated', 'validated', '2026-10-31'],
        ['stock-validated', 'validated', '2026-11-01'],
        ['stock-draft', 'draft', '2026-11-03'],
        ['weekly-unknown-cycle', 'draft', '2026-11-04'],
        ['weekly-draft', 'draft', '2026-11-07'],
    ])->and(($this->summary)('nobody'))->toBe([]);
});

it('drops a validated basket once a correction reverses all its goods (R35a)', function () {
    $when = new DateTimeImmutable('2026-11-03');
    $draft = new Basket('stock-1', 'h', 'Household', BasketState::Draft, null, $when, [
        new BasketLine('line-1', 'stock-1', 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('2'), $when),
    ]);
    $this->baskets->save($draft->validated('manager', $when));
    expect(($this->summary)('h'))->toBe([['stock-1', 'validated', '2026-11-03']]);

    $this->baskets->save($this->baskets->get('stock-1')->withAdjustment(new BasketAdjustment(
        'correction-1', 'stock-1', 1400, 'Returned', 'manager', $when, [new BasketAdjustmentLine('honey', -2000)])));

    expect(($this->summary)('h'))->toBe([]);
});

it('keeps a household that is no longer active settling its baskets through the existing validate and collect actions (R35a)', function () {
    ($this->basket)('weekly-1', 'gone', BasketState::Draft, true, cycle: 'cycle-1');
    ($this->basket)('stock-1', 'gone', BasketState::Draft, false, '2026-11-03');

    ($this->validate)($this->manager, 'weekly-1');
    ($this->validate)($this->manager, 'stock-1');
    expect(($this->summary)('gone'))->toBe([
        ['stock-1', 'validated', '2026-11-03'],
        ['weekly-1', 'validated', '2026-11-07'],
    ]);

    ($this->collect)($this->manager, 'weekly-1');
    expect(($this->summary)('gone'))->toBe([['stock-1', 'validated', '2026-11-03']]);
    ($this->collect)($this->manager, 'stock-1');
    expect(($this->summary)('gone'))->toBe([]);
});
