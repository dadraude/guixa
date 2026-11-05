<?php

use Guixa\Sales\Application\AddReturnToBasket;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\BuildWeeklyBasketLines;
use Guixa\Sales\Application\CorrectBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\CreateWeeklyBasket;
use Guixa\Sales\Application\GetHouseholdContainerBalance;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\SetBasketActualQuantity;
use Guixa\Sales\Application\StockCorrector;
use Guixa\Sales\Application\StockSeller;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Application\WeeklyBaseData;
use Guixa\Sales\Application\WeeklyBaseLineData;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Sales\Domain\ReturnItems;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Sales\Domain\ValueObject\HouseholdSnapshot;
use Guixa\Sales\Domain\ValueObject\ReturnItemSnapshot;
use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;

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
    $weeklySource = new InMemoryWeeklyBasketSource(['order-1' => 'cycle']);
    $this->weeklySource = $weeklySource;
    $validate = new ValidateBasket($this->baskets, $products, $stock, $wallet, $weeklySource, $transactions, $clock);
    $this->validate = $validate;
    $this->collect = new MarkBasketCollected($this->baskets, $transactions, $clock);
    $this->balance = new GetHouseholdContainerBalance($this->baskets);
    $this->correct = new CorrectBasket($this->baskets, $wallet, $stock, $transactions, $clock);
    $this->setActual = new SetBasketActualQuantity($this->baskets, $weeklySource, $transactions);
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
    $this->read = fn (string $household = 'h', ?Actor $actor = null) => ($this->balance)($actor ?? $this->manager, $household);
});

it('accumulates a household balance across independent baskets and accepts a return beyond it (S92, R18a)', function () {
    expect(($this->read)())->toBe(0);

    ($this->validated)('h', ['honey' => '3']);
    expect(($this->read)())->toBe(3);

    ($this->validated)('h', [], 2);
    expect(($this->read)())->toBe(1);

    ($this->validated)('h', ['honey' => '1']);
    expect(($this->read)())->toBe(2);

    ($this->validated)('h', [], 4);
    expect(($this->read)())->toBe(-2);
});

it('does not count draft baskets (R18a)', function () {
    ($this->validated)('h', ['honey' => '3']);
    ($this->draft)('h', ['honey' => '5']);

    expect(($this->read)())->toBe(3);
});

it('rounds up per line and adds nothing for a product without containers (R18a, S60)', function () {
    ($this->validated)('h', ['yoghurt' => '1.300', 'honey' => '3', 'bread' => '4']);
    ($this->validated)('h', ['yoghurt' => '0.500']);

    expect(($this->read)())->toBe(3 + 3 + 1);
});

it('counts the corrected effective quantity, not the original one (R18a, S92)', function () {
    $id = ($this->validated)('h', ['honey' => '3']);
    ($this->correct)($this->manager, $id, -1400, 'Two jars came back', 'fix-1', ['honey' => '2']);

    expect(($this->read)())->toBe(1);
});

it('counts the actual quantity of a validated weekly line (R18a, S60)', function () {
    $line = new WeeklyBaseLineData('line-1', 'yoghurt', 'Yoghurt', 'kg', 400, true, '2', '2.000', '2.000');
    ($this->weekly)(new WeeklyBaseData('order-1', 'cycle', 'h', 'Household H', [$line]));
    $basket = $this->baskets->getBySourceOrder('order-1');
    ($this->setActual)($this->manager, $basket->id, 'yoghurt', '1.300');
    expect(($this->read)())->toBe(0);

    ($this->validate)($this->manager, $basket->id);

    expect(($this->read)())->toBe(3);
});

it('keeps each household separate (S60)', function () {
    ($this->validated)('h', ['honey' => '3']);
    ($this->validated)('k', ['honey' => '1']);

    expect(($this->read)('h'))->toBe(3)->and(($this->read)('k'))->toBe(1);
});

it('lets Finance read any household but a member only their own (R18a)', function () {
    ($this->validated)('h', ['honey' => '3']);
    ($this->validated)('k', ['honey' => '1']);
    $finance = new Actor('finance', [Role::Finance]);

    expect(($this->read)('k', $finance))->toBe(1)
        ->and(($this->read)('h', Actor::memberOf('member', 'h')))->toBe(3)
        ->and(fn () => ($this->read)('k', Actor::memberOf('member', 'h')))->toThrow(AuthorizationException::class);
});

it('keeps counting a validated weekly basket once a new cycleless basket is validated and collected (S80, R19, R24b)', function () {
    $line = new WeeklyBaseLineData('line-1', 'honey', 'Honey', 'jar', 700, false, '1', '2.000', '2.000');
    ($this->weekly)(new WeeklyBaseData('order-1', 'cycle', 'h', 'Household H', [$line]));
    $weekly = $this->baskets->getBySourceOrder('order-1');
    ($this->validate)($this->manager, $weekly->id);
    expect($this->weeklySource->calls)->toBe(['lock:cycle', 'close:cycle'])
        ->and(($this->read)())->toBe(2);

    $late = ($this->draft)('h', ['honey' => '1']);
    ($this->validate)($this->manager, $late);
    ($this->collect)($this->manager, $late);

    expect($this->baskets->get($late)->state->value)->toBe('collected')
        ->and($this->baskets->get($late)->sourceOrderId)->toBeNull()
        ->and($this->weeklySource->cyclesOf([(string) $this->baskets->get($weekly->id)->sourceOrderId]))->toBe(['order-1' => 'cycle'])
        ->and($this->baskets->get($weekly->id)->state->value)->toBe('validated')
        ->and(($this->read)())->toBe(3);
});

it('keeps the containers snapshotted at addition when the product rate changes later (S60, R18a)', function () {
    ($this->validated)('h', ['yoghurt' => '1.300']);
    expect(($this->read)())->toBe(3);

    $this->products->yoghurtContainers = '4';

    expect(($this->read)())->toBe(3);
    ($this->validated)('h', ['yoghurt' => '1.300']);
    expect(($this->read)())->toBe(3 + 6);
});
