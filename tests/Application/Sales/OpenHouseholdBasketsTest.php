<?php

use Guixa\Sales\Application\AddReturnToBasket;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\BuildWeeklyBasketLines;
use Guixa\Sales\Application\CorrectBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\CreateWeeklyBasket;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\ReadOpenHouseholdBaskets;
use Guixa\Sales\Application\SetBasketActualQuantity;
use Guixa\Sales\Application\StockCorrector;
use Guixa\Sales\Application\StockSeller;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Application\WeeklyBasketSource;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Sales\Domain\ReturnItems;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Sales\Domain\ValueObject\HouseholdSnapshot;
use Guixa\Sales\Domain\ValueObject\ReturnItemSnapshot;
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
    $households = new class implements HouseholdDirectory
    {
        public function all(): array
        {
            return [new HouseholdSnapshot('h', 'Household H', true), new HouseholdSnapshot('k', 'Household K', true)];
        }
    };
    $products = new class implements StockProducts
    {
        public string $yoghurtContainers = '2';

        public function lock(string $productId): ?StockProductSnapshot
        {
            return match ($productId) {
                'honey' => new StockProductSnapshot('honey', 'Honey', 'jar', 700, false, '1', true),
                'yoghurt' => new StockProductSnapshot('yoghurt', 'Yoghurt', 'kg', 400, true, $this->yoghurtContainers, true),
                'bread' => new StockProductSnapshot('bread', 'Bread', 'loaf', 300, false, null, true),
                default => null,
            };
        }
    };
    $quantities = new class implements StockQuantities
    {
        public function forProducts(array $ids): array
        {
            return array_fill_keys($ids, '100.000');
        }
    };
    $stock = new class implements StockCorrector, StockSeller
    {
        public function sell(Actor $actor, string $productId, string $quantity, string $basketId): void {}

        public function correct(Actor $actor, string $productId, string $quantity, string $reason, string $basketId, string $correctionId): void {}
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
    $returnItems = new class implements ReturnItems
    {
        public function get(string $id): ?ReturnItemSnapshot
        {
            return $id === 'jar' ? new ReturnItemSnapshot('jar', 'Glass jar', 50, true) : null;
        }
    };
    $create = new CreateStockBasket($this->baskets, $households, $clock);
    $add = new AddStockToBasket($this->baskets, $products, $quantities, $transactions, $clock);
    $addReturn = new AddReturnToBasket($this->baskets, $returnItems, $transactions, $clock);
    $this->products = $products;
    $this->closedCycles = [];
    $weeklySource = new class($this) implements WeeklyBasketSource
    {
        public function __construct(private readonly object $test) {}

        public function lockSettled(string $cycleId): void {}

        public function closeIfFinished(string $cycleId): void
        {
            $this->test->closedCycles[] = $cycleId;
        }
    };
    $validate = new ValidateBasket($this->baskets, $products, $stock, $wallet, $weeklySource, $transactions, $clock);
    $this->validate = $validate;
    $this->collect = new MarkBasketCollected($this->baskets, $transactions, $clock);
    $this->open = new ReadOpenHouseholdBaskets($this->baskets);
    $this->correct = new CorrectBasket($this->baskets, $wallet, $stock, $transactions, $clock);
    $this->setActual = new SetBasketActualQuantity($this->baskets, new NoWeeklyBasketSource, $transactions);
    $this->weekly = new CreateWeeklyBasket($this->baskets, new BuildWeeklyBasketLines($this->baskets, $clock), $clock);

    /** Creates a basket for the household, fills it with product => quantity and return jars, and returns its id. */
    $this->draft = function (string $household, array $goods = [], int $jars = 0) use ($create, $add, $addReturn): string {
        $id = $create($this->manager, $household);
        foreach ($goods as $product => $quantity) {
            $add($this->manager, $id, $product, $quantity);
        }
        if ($jars > 0) {
            $addReturn($this->manager, $id, 'jar', (string) $jars);
        }

        return $id;
    };
    $this->validated = function (string $household, array $goods = [], int $jars = 0) use ($validate): string {
        $id = ($this->draft)($household, $goods, $jars);
        $validate($this->manager, $id);

        return $id;
    };
});

it('lists a draft basket and a validated basket still to collect, never a collected one (R35a)', function () {
    $draft = ($this->draft)('h', ['honey' => '1']);
    $validated = ($this->validated)('h', ['bread' => '2']);
    $collected = ($this->validated)('h', ['honey' => '1']);
    ($this->collect)($this->manager, $collected);
    ($this->validated)('k', ['honey' => '1']);

    $open = ($this->open)('h');

    expect(array_map(fn ($basket) => [$basket->basketId, $basket->status], $open))
        ->toEqualCanonicalizing([[$draft, 'draft'], [$validated, 'validated']]);
});

it('drops a validated basket once a correction reverses all its goods (R35a)', function () {
    $id = ($this->validated)('h', ['honey' => '2']);
    expect(($this->open)('h'))->toHaveCount(1);

    ($this->correct)($this->manager, $id, 1400, 'Returned', 'corr-1', ['honey' => '2']);

    expect(($this->open)('h'))->toBe([]);
});

it('keeps a weekly and a return basket of a household that left the directory open until staff validate and collect them (R35a)', function () {
    $when = new DateTimeImmutable('2026-11-06');
    $this->baskets->save(new Basket('weekly-1', 'gone', 'Gone', BasketState::Draft, null, $when, [
        new BasketLine('line-1', 'weekly-1', 'bread', 'Bread', 'loaf', 300, false, null, Quantity::fromString('1'), $when, kind: BasketLineKind::Weekly),
    ], kind: BasketKind::Weekly, referenceCycleId: 'cycle-1'));
    $this->baskets->save(new Basket('stock-1', 'gone', 'Gone', BasketState::Draft, null, $when, [
        new BasketLine('line-2', 'stock-1', null, 'Jar', 'jar', 50, false, null, Quantity::fromString('1'), $when, kind: BasketLineKind::Return, returnItemId: 'jar'),
    ]));

    expect(array_column(array_map(fn ($b) => (array) $b, ($this->open)('gone')), 'basketId'))->toEqualCanonicalizing(['weekly-1', 'stock-1']);

    ($this->validate)($this->manager, 'weekly-1');
    ($this->validate)($this->manager, 'stock-1');
    expect(($this->open)('gone'))->toHaveCount(2);

    ($this->collect)($this->manager, 'weekly-1');
    ($this->collect)($this->manager, 'stock-1');
    expect(($this->open)('gone'))->toBe([]);
});
