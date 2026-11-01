<?php

use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Application\CancelDraftBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\GetBasketBalance;
use Guixa\Sales\Application\HasProductHistory;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\RemoveStockFromBasket;
use Guixa\Sales\Application\StockSeller;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketAlreadyValidated;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\HouseholdUnavailable;
use Guixa\Sales\Domain\Error\InvalidBasketQuantity;
use Guixa\Sales\Domain\Error\NotEnoughBasketStock;
use Guixa\Sales\Domain\Error\StockProductUnavailable;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Sales\Domain\ValueObject\HouseholdSnapshot;
use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;

beforeEach(function () {
    $this->actor = Actor::manager('manager');
    $this->clock = new FrozenClock('2026-10-30 10:00:00');
    $this->transactions = new ImmediateTransactionRunner;
    $this->baskets = new class implements BasketRepository
    {
        public array $rows = [];

        private int $sequence = 0;

        public function nextId(): string
        {
            return 'id-'.++$this->sequence;
        }

        public function save(Basket $basket): void
        {
            $this->rows[$basket->id] = $basket;
        }

        public function get(string $id): ?Basket
        {
            return $this->rows[$id] ?? null;
        }

        public function lock(string $id): ?Basket
        {
            return $this->get($id);
        }

        public function list(): array
        {
            return array_values($this->rows);
        }

        public function hasProductHistory(string $productId): bool
        {
            foreach ($this->rows as $basket) {
                foreach ($basket->lines as $line) {
                    if ($line->productId === $productId) {
                        return true;
                    }
                }
            }

            return false;
        }
    };
    $this->households = new class implements HouseholdDirectory
    {
        public bool $active = true;

        public function all(): array
        {
            return [new HouseholdSnapshot('household', 'Household K', $this->active)];
        }
    };
    $this->products = new class implements StockProducts
    {
        public int $priceCents = 1200;

        public bool $active = true;

        public bool $variable = false;

        public function lock(string $productId): ?StockProductSnapshot
        {
            return $productId === 'oil' ? new StockProductSnapshot('oil', 'Oil', 'bottle', $this->priceCents, $this->variable, null, $this->active) : null;
        }
    };
    $this->quantities = new class implements StockQuantities
    {
        public string $available = '5.000';

        public function forProducts(array $ids): array
        {
            return array_fill_keys($ids, $this->available);
        }
    };
    $this->stock = new class implements StockSeller
    {
        public array $sales = [];

        public function sell(Actor $actor, string $productId, string $quantity, string $basketId): void
        {
            $this->sales[] = [$actor->accountId, $productId, $quantity, $basketId];
        }
    };
    $this->wallet = new class implements WalletLedger
    {
        public array $charges = [];

        public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
        {
            $this->charges[] = [$actor->accountId, $householdId, $basketId, $totalCents];
        }

        public function balance(Actor $actor, string $householdId): int
        {
            return 500;
        }
    };
    $this->create = new CreateStockBasket($this->baskets, $this->households, $this->clock);
    $this->add = new AddStockToBasket($this->baskets, $this->products, $this->quantities, $this->transactions, $this->clock);
    $this->validate = new ValidateBasket($this->baskets, $this->products, $this->stock, $this->wallet, $this->transactions, $this->clock);
    $this->collect = new MarkBasketCollected($this->baskets, $this->transactions, $this->clock);
    $this->remove = new RemoveStockFromBasket($this->baskets, $this->transactions, $this->clock);
    $this->cancel = new CancelDraftBasket($this->baskets, $this->transactions, $this->clock);
});

it('creates, snapshots, validates and collects an independent stock basket once (S18, S79, S22)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    $draft = $this->baskets->get($id);
    expect($draft->state)->toBe('draft')
        ->and($draft->lines[0]->name)->toBe('Oil')
        ->and($draft->lines[0]->priceCents)->toBe(1200)
        ->and($this->stock->sales)->toBe([])
        ->and($this->wallet->charges)->toBe([]);

    ($this->validate)($this->actor, $id);
    expect($this->baskets->get($id)->state)->toBe('validated')
        ->and($this->stock->sales)->toHaveCount(1)
        ->and($this->wallet->charges)->toBe([['manager', 'household', $id, 1200]]);

    ($this->collect)($this->actor, $id);
    $firstCollection = $this->baskets->get($id)->collectedAt;
    ($this->collect)($this->actor, $id);
    expect($this->baskets->get($id)->state)->toBe('collected')
        ->and($this->baskets->get($id)->collectedAt)->toEqual($firstCollection)
        ->and($this->stock->sales)->toHaveCount(1)
        ->and($this->wallet->charges)->toHaveCount(1);
});

it('validates real zero-price goods without asking Wallet to create a movement (R22)', function () {
    $this->products->priceCents = 0;
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    ($this->validate)($this->actor, $id);

    expect($this->baskets->get($id)->validatedTotalCents)->toBe(0)
        ->and($this->stock->sales)->toHaveCount(1)
        ->and($this->wallet->charges)->toBe([]);
});

it('requires a Manager and an active household for a new independent draft (R19, R19a)', function () {
    expect(fn () => ($this->create)(Actor::memberOf('member', 'household'), 'household'))->toThrow(NotAllowed::class);
    $this->households->active = false;
    expect(fn () => ($this->create)($this->actor, 'household'))->toThrow(HouseholdUnavailable::class);
    expect($this->baskets->list())->toBe([]);
});

it('checks stock identity, positive step and combined availability before retaining a draft line (R20, S17)', function () {
    $id = ($this->create)($this->actor, 'household');
    expect(fn () => ($this->add)($this->actor, $id, 'weekly-or-unknown', '1'))->toThrow(StockProductUnavailable::class);
    expect(fn () => ($this->add)($this->actor, $id, 'oil', '0'))->toThrow(InvalidBasketQuantity::class);
    expect(fn () => ($this->add)($this->actor, $id, 'oil', '0.001'))->toThrow(InvalidBasketQuantity::class);
    ($this->add)($this->actor, $id, 'oil', '3');
    expect(fn () => ($this->add)($this->actor, $id, 'oil', '3'))->toThrow(NotEnoughBasketStock::class);
    expect($this->baskets->get($id)->activeLineFor('oil')->quantity->toString())->toBe('3.000')
        ->and($this->stock->sales)->toBe([])
        ->and($this->wallet->charges)->toBe([]);
});

it('permits a 0.001 variable step and preserves the original price on an increase (R20)', function () {
    $this->products->variable = true;
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '0.001');
    $this->products->priceCents = 1500;
    ($this->add)($this->actor, $id, 'oil', '0.124');
    expect($this->baskets->get($id)->activeLineFor('oil')->quantity->toString())->toBe('0.125')
        ->and($this->baskets->get($id)->activeLineFor('oil')->priceCents)->toBe(1200);
});

it('retains removed and cancelled draft references but excludes them from current contents (R19b, S87)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    ($this->remove)($this->actor, $id, 'oil');
    expect($this->baskets->get($id)->activeLines())->toBe([])
        ->and((new HasProductHistory($this->baskets))('oil'))->toBeTrue();
    $this->products->priceCents = 1500;
    ($this->add)($this->actor, $id, 'oil', '1');
    expect($this->baskets->get($id)->lines)->toHaveCount(2)
        ->and($this->baskets->get($id)->activeLineFor('oil')->priceCents)->toBe(1500);
    ($this->cancel)($this->actor, $id);
    expect($this->baskets->get($id)->state)->toBe('cancelled')
        ->and($this->baskets->get($id)->activeLines())->toBe([])
        ->and($this->baskets->get($id)->totalCents())->toBe(0)
        ->and(BasketData::from($this->baskets->get($id))->stockLines)->toBe([])
        ->and(BasketData::from($this->baskets->get($id))->totalCents)->toBe(0)
        ->and((new HasProductHistory($this->baskets))('oil'))->toBeTrue()
        ->and($this->wallet->charges)->toBe([]);
});

it('hides active contents of a cancelled populated draft while retaining its original line (R19b)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    ($this->cancel)($this->actor, $id);

    $basket = $this->baskets->get($id);
    expect($basket->lines)->toHaveCount(1)
        ->and($basket->activeLines())->toBe([])
        ->and($basket->totalCents())->toBe(0)
        ->and(BasketData::from($basket)->stockLines)->toBe([])
        ->and(BasketData::from($basket)->totalCents)->toBe(0)
        ->and((new HasProductHistory($this->baskets))('oil'))->toBeTrue()
        ->and($this->stock->sales)->toBe([])
        ->and($this->wallet->charges)->toBe([]);
});

it('validates a prior snapshot after deactivation while refusing an increase (S55)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    $this->products->active = false;
    expect(fn () => ($this->add)($this->actor, $id, 'oil', '1'))->toThrow(StockProductUnavailable::class);
    ($this->validate)($this->actor, $id);
    expect($this->baskets->get($id)->validatedTotalCents)->toBe(1200);
});

it('rejects replay and edits after validation without extra ledger writes (S22)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    ($this->validate)($this->actor, $id);
    expect(fn () => ($this->validate)($this->actor, $id))->toThrow(BasketAlreadyValidated::class);
    expect(fn () => ($this->remove)($this->actor, $id, 'oil'))->toThrow(BasketNotEditable::class);
    expect($this->stock->sales)->toHaveCount(1)
        ->and($this->wallet->charges)->toHaveCount(1);
});

it('reads the global household balance through the Sales Application port (R21)', function () {
    expect((new GetBasketBalance($this->wallet))($this->actor, 'household'))->toBe(500);
});

it('accepts the same Finance Actor through every public independent-basket use case (R19a, S79, S84)', function () {
    $finance = new Actor('finance', [Role::Finance]);
    $id = ($this->create)($finance, 'household');
    ($this->add)($finance, $id, 'oil', '1');
    ($this->validate)($finance, $id);
    ($this->collect)($finance, $id);

    expect($this->baskets->get($id)->state)->toBe('collected')
        ->and($this->stock->sales[0][0])->toBe('finance')
        ->and($this->wallet->charges[0][0])->toBe('finance');
});
