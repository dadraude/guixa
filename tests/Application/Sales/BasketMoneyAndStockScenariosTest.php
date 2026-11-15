<?php

use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\BuildWeeklyBasketLines;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\CreateWeeklyBasket;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WeeklyBaseData;
use Guixa\Sales\Application\WeeklyBaseLineData;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Sales\Domain\ValueObject\HouseholdSnapshot;
use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;
use Guixa\Sales\Infrastructure\Stock\StockBasketSeller;
use Guixa\Sales\Infrastructure\Wallet\WalletBasketLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Stock\Application\GetAvailableQuantities;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\SellBasketStock;
use Guixa\Stock\Domain\Error\NotEnoughStock;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Wallet\Application\ChargeForBasket;
use Guixa\Wallet\Application\GetBalance;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryMovementRepository;
use Tests\Support\InMemory\InMemoryStockMovementRepository;
use Tests\Support\InMemory\InMemoryStockProductRepository;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;

/** Sales, Wallet and Stock use cases wired over the in-memory repositories, so a validation moves real money and stock. */
beforeEach(function () {
    $this->staff = Actor::manager('manager');
    $this->clock = new FrozenClock('2026-11-07 10:00:00');
    $transactions = new ImmediateTransactionRunner;
    $this->baskets = new InMemoryBasketRepository;
    $this->movements = new InMemoryMovementRepository;
    $this->stockMovements = new InMemoryStockMovementRepository;
    $this->catalog = new InMemoryStockProductRepository;
    $this->source = new InMemoryWeeklyBasketSource(['order-1' => 'cycle-a']);

    $ledger = new StockLedger($this->stockMovements);
    $this->entry = new RecordStockEntry($this->catalog, $this->stockMovements, $ledger, $transactions, $this->clock);
    $sell = new SellBasketStock($this->catalog, $this->stockMovements, $ledger, $transactions, $this->clock);
    $this->topUp = new RecordTopUp($this->movements, $this->clock);
    $this->correct = new RecordCorrection($this->movements, $this->clock);
    $wallet = new WalletBasketLedger(new ChargeForBasket($this->movements, $this->clock), new GetBalance($this->movements), $this->correct);

    $catalog = $this->catalog;
    $products = new class($catalog) implements StockProducts
    {
        public function __construct(private readonly InMemoryStockProductRepository $catalog) {}

        public function lock(string $productId): ?StockProductSnapshot
        {
            $product = $this->catalog->get($productId);

            return $product === null ? null : new StockProductSnapshot($product->id, $product->name(), $product->unit(),
                $product->price()->cents, $product->isVariable(), $product->containersPerUnit()?->toString(), $product->isActive());
        }
    };
    $available = new GetAvailableQuantities($this->stockMovements);
    $quantities = new class($available) implements StockQuantities
    {
        public function __construct(private readonly GetAvailableQuantities $available) {}

        public function forProducts(array $ids): array
        {
            return ($this->available)($ids);
        }
    };
    $households = new class implements HouseholdDirectory
    {
        public function all(): array
        {
            return [new HouseholdSnapshot('h', 'Household H', true)];
        }
    };

    $this->create = new CreateStockBasket($this->baskets, $households, $this->clock);
    $this->add = new AddStockToBasket($this->baskets, $products, $quantities, $transactions, $this->clock);
    $this->validate = new ValidateBasket($this->baskets, $products, new StockBasketSeller($sell), $wallet, $this->source, $transactions, $this->clock);
    $this->collect = new MarkBasketCollected($this->baskets, $transactions, $this->clock);
    $this->createWeekly = new CreateWeeklyBasket($this->baskets, new BuildWeeklyBasketLines($this->baskets, $this->clock), $this->clock);

    $this->product = function (string $name, int $priceCents, string $stock): string {
        $product = $this->catalog->create($name, 'jar', Money::ofCents($priceCents), false, null);
        ($this->entry)($this->staff, $product->id, $stock, null);

        return $product->id;
    };
    $this->draft = function (string $productId, string $quantity): string {
        $basket = ($this->create)($this->staff, 'h');
        ($this->add)($this->staff, $basket, $productId, $quantity);

        return $basket;
    };
    $this->balance = fn () => $this->movements->balanceOf('h')->cents;
    $this->left = fn (string $productId) => $this->stockMovements->available($productId)->toString();
});

it('keeps a confirmed receipt separate from the basket charge: 5 + 40 - 30 gives 15, and collection moves nothing (R21, R22, R29, S19)', function () {
    $oil = ($this->product)('Oil', 3000, '2');
    ($this->topUp)($this->staff, 'h', 500, null);
    $basket = ($this->draft)($oil, '1');

    $receipt = ($this->topUp)($this->staff, 'h', 4000, 'cash');
    ($this->validate)($this->staff, $basket);

    $statement = collect($this->movements->statementOf('h'));
    expect(($this->balance)())->toBe(1500)
        ->and($statement->firstWhere('id', $receipt)->basketId)->toBeNull()
        ->and($statement->firstWhere('id', $receipt)->amount->cents)->toBe(4000)
        ->and($statement->whereNotNull('basketId')->map(fn ($movement) => [$movement->basketId, $movement->amount->cents])->values()->all())->toBe([[$basket, -3000]]);

    ($this->collect)($this->staff, $basket);
    expect(($this->balance)())->toBe(1500)
        ->and($this->movements->statementOf('h'))->toHaveCount(3)
        ->and(($this->left)($oil))->toBe('1.000');
});

it('leaves a negative balance of -25 after a 30 basket on a balance of 5, and collection stays available (R11, R22, R30, S21)', function () {
    $oil = ($this->product)('Oil', 3000, '2');
    ($this->topUp)($this->staff, 'h', 500, null);
    $basket = ($this->draft)($oil, '1');

    ($this->validate)($this->staff, $basket);
    ($this->collect)(Actor::memberOf('anna', 'h'), $basket);

    expect(($this->balance)())->toBe(-2500)
        ->and($this->baskets->get($basket)->state)->toBe(BasketState::Collected);
});

it('sells remaining stock to a household without a cycle or weekly source: 2 of 5 jars cost 12 and 3 remain (R19, R20, R22, S75)', function () {
    $honey = ($this->product)('Honey', 600, '5');
    $basket = ($this->draft)($honey, '2');

    ($this->validate)($this->staff, $basket);

    expect(($this->balance)())->toBe(-1200)
        ->and(($this->left)($honey))->toBe('3.000')
        ->and($this->source->calls)->toBe([])
        ->and($this->baskets->get($basket)->sourceOrderId)->toBeNull();
});

it('adds stock to a weekly draft and charges both once: 20 + 2 x 6 = 32 with 3 jars left, and collection has no effect (R20, R22, R28, S16)', function () {
    $honey = ($this->product)('Honey', 600, '5');
    ($this->createWeekly)(new WeeklyBaseData('order-1', 'cycle-a', 'h', 'Household H', [
        new WeeklyBaseLineData('line-bread', 'bread', 'Bread', 'loaf', 500, false, null, '4', '4'),
    ]));
    $weekly = $this->baskets->getBySourceOrder('order-1');
    ($this->add)($this->staff, $weekly->id, $honey, '2');

    ($this->validate)($this->staff, $weekly->id);
    ($this->collect)($this->staff, $weekly->id);

    expect(($this->balance)())->toBe(-3200)
        ->and(($this->left)($honey))->toBe('3.000')
        ->and($this->movements->statementOf('h'))->toHaveCount(1);
});

it('rejects the second validation when the first took the stock: the draft and the Wallet stay as they were (R20, R22, R28, S81)', function () {
    $honey = ($this->product)('Honey', 600, '5');
    $first = ($this->draft)($honey, '3');
    $second = ($this->draft)($honey, '3');

    ($this->validate)($this->staff, $first);
    $balance = ($this->balance)();

    expect(fn () => ($this->validate)($this->staff, $second))->toThrow(NotEnoughStock::class)
        ->and($this->baskets->get($second)->state)->toBe(BasketState::Draft)
        ->and(($this->balance)())->toBe($balance)
        ->and(($this->left)($honey))->toBe('2.000');
});

it('charges a weekly basket only for its own contents and collection adds no movement (R22, R23b, S9)', function () {
    ($this->createWeekly)(new WeeklyBaseData('order-1', 'cycle-a', 'h', 'Household H', [
        new WeeklyBaseLineData('line-bread', 'bread', 'Bread', 'loaf', 350, false, null, '2', '2'),
    ]));
    $weekly = $this->baskets->getBySourceOrder('order-1');

    ($this->validate)($this->staff, $weekly->id);
    ($this->collect)(Actor::memberOf('anna', 'h'), $weekly->id);

    expect(($this->balance)())->toBe(-700)
        ->and($this->movements->statementOf('h'))->toHaveCount(1)
        ->and($this->source->calls)->toBe(['lock:cycle-a', 'close:cycle-a'])
        ->and($this->stockMovements->forProduct('bread'))->toBe([]);
});

it('keeps a validated basket pending and charged once after its pickup day passes, and lets it be collected later (R24, S42)', function () {
    ($this->createWeekly)(new WeeklyBaseData('order-1', 'cycle-a', 'h', 'Household H', [
        new WeeklyBaseLineData('line-bread', 'bread', 'Bread', 'loaf', 300, false, null, '2', '2'),
    ]));
    $weekly = $this->baskets->getBySourceOrder('order-1');
    ($this->validate)($this->staff, $weekly->id);

    $this->clock->travelTo('2026-11-20 09:00:00');

    expect($this->baskets->get($weekly->id)->state)->toBe(BasketState::Validated)
        ->and(array_map(fn ($basket) => $basket->id, $this->baskets->pendingCollections('h')))->toBe([$weekly->id])
        ->and(($this->balance)())->toBe(-600);
    ($this->collect)(Actor::memberOf('anna', 'h'), $weekly->id);
    expect($this->baskets->get($weekly->id)->state)->toBe(BasketState::Collected)
        ->and($this->movements->statementOf('h'))->toHaveCount(1);
});

it('applies one global receipt over two basket charges and a receipt correction appends without touching receipt or baskets (R21, R29, R30, R32, S85)', function () {
    $oil = ($this->product)('Oil', 1500, '4');
    $first = ($this->draft)($oil, '1');
    $second = ($this->draft)($oil, '1');
    ($this->validate)($this->staff, $first);
    ($this->validate)($this->staff, $second);
    expect(($this->balance)())->toBe(-3000);

    $receipt = ($this->topUp)($this->staff, 'h', 5000, 'cash');
    expect(($this->balance)())->toBe(2000)
        ->and([$this->baskets->get($first)->state, $this->baskets->get($second)->state])->toBe([BasketState::Validated, BasketState::Validated]);

    ($this->correct)($this->staff, 'h', -5000, 'duplicated', null, $receipt, 'correction-1');

    expect(($this->balance)())->toBe(-3000)
        ->and($this->movements->findById($receipt)->amount->cents)->toBe(5000)
        ->and($this->movements->statementOf('h'))->toHaveCount(4)
        ->and([$this->baskets->get($first)->validatedTotalCents, $this->baskets->get($second)->validatedTotalCents])->toBe([1500, 1500]);
});
