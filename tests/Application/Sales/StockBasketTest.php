<?php

use Guixa\Sales\Application\AddReturnToBasket;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Application\CancelDraftBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\GetBasketBalance;
use Guixa\Sales\Application\HasProductHistory;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\RemoveReturnFromBasket;
use Guixa\Sales\Application\RemoveStockFromBasket;
use Guixa\Sales\Application\StockSeller;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketAlreadyValidated;
use Guixa\Sales\Domain\Error\BasketHasNoContents;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\HouseholdUnavailable;
use Guixa\Sales\Domain\Error\InvalidBasketQuantity;
use Guixa\Sales\Domain\Error\InvalidReturnQuantity;
use Guixa\Sales\Domain\Error\NotEnoughBasketStock;
use Guixa\Sales\Domain\Error\ReturnItemUnavailable;
use Guixa\Sales\Domain\Error\StockProductUnavailable;
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
use Tests\Support\InMemory\Sales\NoWeeklyBasketSource;

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

        public function getBySourceOrder(string $orderId): ?Basket
        {
            return null;
        }

        public function getOpenLateBySourceOrder(string $orderId): ?Basket
        {
            return null;
        }

        public function weeklyOfSourceOrders(array $sourceOrderIds): array
        {
            return [];
        }

        public function lock(string $id): ?Basket
        {
            return $this->get($id);
        }

        public function list(): array
        {
            return array_values($this->rows);
        }

        public function getForHousehold(string $id, string $householdId): ?Basket
        {
            $basket = $this->get($id);

            return $basket?->householdId === $householdId ? $basket : null;
        }

        public function listForHousehold(string $householdId): array
        {
            return array_values(array_filter($this->list(),
                fn (Basket $basket) => $basket->householdId === $householdId));
        }

        public function settledForHouseholds(array $householdIds): array
        {
            return [];
        }

        public function pendingCollections(?string $householdId = null): array
        {
            $pending = array_filter($this->list(), fn (Basket $basket) => $basket->isPendingCollection()
                && ($householdId === null || $basket->householdId === $householdId));
            usort($pending, fn (Basket $a, Basket $b) => [$a->validatedAt, $a->id] <=> [$b->validatedAt, $b->id]);

            return array_values($pending);
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

        public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void {}
    };
    $this->returnItems = new class implements ReturnItems
    {
        public string $name = 'Glass jar';

        public int $amountCents = 50;

        public bool $active = true;

        public function get(string $id): ?ReturnItemSnapshot
        {
            return $id === 'jar' ? new ReturnItemSnapshot('jar', $this->name, $this->amountCents, $this->active) : null;
        }
    };
    $this->create = new CreateStockBasket($this->baskets, $this->households, $this->clock);
    $this->add = new AddStockToBasket($this->baskets, $this->products, $this->quantities, $this->transactions, $this->clock);
    $this->addReturn = new AddReturnToBasket($this->baskets, $this->returnItems, $this->transactions, $this->clock);
    $this->validate = new ValidateBasket($this->baskets, $this->products, $this->stock, $this->wallet, new NoWeeklyBasketSource, $this->transactions, $this->clock);
    $this->collect = new MarkBasketCollected($this->baskets, $this->transactions, $this->clock);
    $this->remove = new RemoveStockFromBasket($this->baskets, $this->transactions, $this->clock);
    $this->cancel = new CancelDraftBasket($this->baskets, $this->transactions, $this->clock);
});

it('creates, snapshots, validates and collects an independent stock basket once (S18, S79, S22)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    $draft = $this->baskets->get($id);
    expect($draft->state)->toBe(BasketState::Draft)
        ->and($draft->lines[0]->name)->toBe('Oil')
        ->and($draft->lines[0]->priceCents)->toBe(1200)
        ->and($this->stock->sales)->toBe([])
        ->and($this->wallet->charges)->toBe([]);

    ($this->validate)($this->actor, $id);
    expect($this->baskets->get($id)->state)->toBe(BasketState::Validated)
        ->and($this->stock->sales)->toHaveCount(1)
        ->and($this->wallet->charges)->toBe([['manager', 'household', $id, 1200]]);

    ($this->collect)($this->actor, $id);
    $firstCollection = $this->baskets->get($id)->collectedAt;
    ($this->collect)($this->actor, $id);
    expect($this->baskets->get($id)->state)->toBe(BasketState::Collected)
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

it('credits a return-only basket through the public validator (S88, R20a, R22)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->addReturn)($this->actor, $id, 'jar', '1');

    ($this->validate)($this->actor, $id);

    expect($this->baskets->get($id)->validatedTotalCents)->toBe(-50)
        ->and($this->stock->sales)->toBe([])
        ->and($this->wallet->charges)->toBe([['manager', 'household', $id, -50]]);
});

it('charges exactly eight euros for ten euros of goods and four jar returns (S41, R20a, R22)', function () {
    $this->products->priceCents = 1000;
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    ($this->addReturn)($this->actor, $id, 'jar', '4');
    ($this->validate)($this->actor, $id);

    expect($this->baskets->get($id)->validatedTotalCents)->toBe(800)
        ->and($this->stock->sales)->toHaveCount(1)
        ->and($this->wallet->charges)->toBe([['manager', 'household', $id, 800]]);
});

it('validates real-content net-zero goods without a Wallet movement (S88, R22)', function () {
    $this->products->priceCents = 50;
    $id = ($this->create)($this->actor, 'household');
    ($this->add)($this->actor, $id, 'oil', '1');
    ($this->addReturn)($this->actor, $id, 'jar', '1');
    ($this->validate)($this->actor, $id);
    ($this->collect)($this->actor, $id);

    expect($this->baskets->get($id)->state)->toBe(BasketState::Collected)
        ->and($this->baskets->get($id)->validatedTotalCents)->toBe(0)
        ->and($this->wallet->charges)->toBe([]);
});

it('rejects empty validation but cancels an empty draft without money or stock effects (S88, R24a)', function () {
    $id = ($this->create)($this->actor, 'household');
    expect(fn () => ($this->validate)($this->actor, $id))->toThrow(BasketHasNoContents::class);
    ($this->cancel)($this->actor, $id);
    expect($this->baskets->get($id)->state)->toBe(BasketState::Cancelled)
        ->and($this->wallet->charges)->toBe([])
        ->and($this->stock->sales)->toBe([]);
});

it('allows a known inactive return item but requires a positive whole quantity (R20a)', function () {
    $id = ($this->create)($this->actor, 'household');
    expect(fn () => ($this->addReturn)($this->actor, $id, 'jar', '0'))->toThrow(InvalidReturnQuantity::class);
    expect(fn () => ($this->addReturn)($this->actor, $id, 'jar', '0.5'))->toThrow(InvalidReturnQuantity::class);
    expect(fn () => ($this->addReturn)($this->actor, $id, 'jar', '-1'))->toThrow(InvalidReturnQuantity::class);
    expect(fn () => ($this->addReturn)($this->actor, $id, 'unknown', '1'))->toThrow(ReturnItemUnavailable::class);
    $this->returnItems->active = false;
    ($this->addReturn)($this->actor, $id, 'jar', '1');
    expect($this->baskets->get($id)->activeLines()[0]->quantity->toString())->toBe('1.000');
});

it('keeps a return snapshot on increase and archives it before re-add (R20a, R24a)', function () {
    $id = ($this->create)($this->actor, 'household');
    ($this->addReturn)($this->actor, $id, 'jar', '1');
    $this->returnItems->name = 'Changed jar';
    $this->returnItems->amountCents = 75;
    $this->returnItems->active = false;
    ($this->addReturn)($this->actor, $id, 'jar', '1');
    expect($this->baskets->get($id)->activeLines()[0]->name)->toBe('Glass jar')
        ->and($this->baskets->get($id)->activeLines()[0]->priceCents)->toBe(50)
        ->and($this->baskets->get($id)->totalCents())->toBe(-100);

    $remove = new RemoveReturnFromBasket($this->baskets, $this->transactions, $this->clock);
    $remove($this->actor, $id, 'jar');
    ($this->addReturn)($this->actor, $id, 'jar', '1');
    $basket = $this->baskets->get($id);
    expect($basket->lines)->toHaveCount(2)
        ->and($basket->lines[0]->removedAt)->not->toBeNull()
        ->and($basket->activeLines()[0]->name)->toBe('Changed jar')
        ->and($basket->totalCents())->toBe(-75);
});

it('rejects initial and combined return quantities above the representable bound without changing the draft (R20a)', function () {
    $id = ($this->create)($this->actor, 'household');
    expect(fn () => ($this->addReturn)($this->actor, $id, 'jar', '10000000'))->toThrow(InvalidReturnQuantity::class);
    expect($this->baskets->get($id)->lines)->toBe([]);

    ($this->addReturn)($this->actor, $id, 'jar', '9999999');
    $before = $this->baskets->get($id);
    expect(fn () => ($this->addReturn)($this->actor, $id, 'jar', '1'))->toThrow(InvalidReturnQuantity::class);
    expect($this->baskets->get($id))->toBe($before)
        ->and($this->baskets->get($id)->activeLines()[0]->quantity->toString())->toBe('9999999.000')
        ->and($this->wallet->charges)->toBe([])
        ->and($this->stock->sales)->toBe([]);
});

it('requires a Manager and an active household for a new independent draft (R19, R19a)', function () {
    expect(fn () => ($this->create)(Actor::memberOf('member', 'household'), 'household'))->toThrow(AuthorizationException::class);
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
    expect($this->baskets->get($id)->state)->toBe(BasketState::Cancelled)
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

    expect($this->baskets->get($id)->state)->toBe(BasketState::Collected)
        ->and($this->stock->sales[0][0])->toBe('finance')
        ->and($this->wallet->charges[0][0])->toBe('finance');
});
