<?php

use Guixa\Sales\Application\AddLateWeeklyLines;
use Guixa\Sales\Application\BuildWeeklyBasketLines;
use Guixa\Sales\Application\CancelDraftBasket;
use Guixa\Sales\Application\CreateWeeklyBasket;
use Guixa\Sales\Application\ReadPendingWeeklyCycles;
use Guixa\Sales\Application\ReadWeeklyBasketStates;
use Guixa\Sales\Application\SetBasketActualQuantity;
use Guixa\Sales\Application\StockSeller;
use Guixa\Sales\Application\SyncWeeklyBasket;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Application\WeeklyBaseData;
use Guixa\Sales\Application\WeeklyBaseLineData;
use Guixa\Sales\Application\WeeklyBasketSource;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketAlreadyValidated;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\InvalidBasketQuantity;
use Guixa\Sales\Domain\StockProducts;
use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

/** A line of a source order: bread (whole units) or beef (by weight). */
function sourceLine(string $product, string $ordered, string $allocated): WeeklyBaseLineData
{
    $price = ['bread' => 350, 'beef' => 2000][$product];

    return new WeeklyBaseLineData("line-{$product}", $product, ucfirst($product), $product === 'beef' ? 'kg' : 'loaf', $price, $product === 'beef', null, $ordered, $allocated);
}

/** @param  list<WeeklyBaseLineData>  $lines */
function sourceOf(array $lines, string $order = 'order-1', string $household = 'household'): WeeklyBaseData
{
    return new WeeklyBaseData($order, 'cycle', $household, 'Household K', $lines);
}

beforeEach(function () {
    $this->staff = Actor::manager('manager');
    $this->clock = new FrozenClock('2026-11-07 10:00:00');
    $this->baskets = new InMemoryBasketRepository;
    $this->transactions = new ImmediateTransactionRunner;
    $this->source = new class implements WeeklyBasketSource
    {
        public array $calls = [];

        public function lockSettled(string $cycleId): void
        {
            $this->calls[] = "lock:{$cycleId}";
        }

        public function closeIfFinished(string $cycleId): void
        {
            $this->calls[] = "close:{$cycleId}";
        }
    };
    $this->wallet = new class implements WalletLedger
    {
        public array $charges = [];

        public array $corrections = [];

        public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
        {
            $this->charges[] = [$basketId, $totalCents];
        }

        public function balance(Actor $actor, string $householdId): int
        {
            return 0;
        }

        public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
        {
            $this->corrections[] = [$basketId, $amountCents];
        }
    };
    $this->stock = new class implements StockSeller
    {
        public int $sales = 0;

        public function sell(Actor $actor, string $productId, string $quantity, string $basketId): void
        {
            $this->sales++;
        }
    };
    $products = new class implements StockProducts
    {
        public function lock(string $productId): ?StockProductSnapshot
        {
            return new StockProductSnapshot($productId, 'Oil', 'bottle', 1200, false, null, true);
        }
    };
    $lines = new BuildWeeklyBasketLines($this->baskets, $this->clock);
    $this->create = new CreateWeeklyBasket($this->baskets, $lines, $this->clock);
    $this->sync = new SyncWeeklyBasket($this->baskets, $this->create, $lines, $this->clock);
    $this->setActual = new SetBasketActualQuantity($this->baskets, $this->source, $this->transactions);
    $this->validate = new ValidateBasket($this->baskets, $products, $this->stock, $this->wallet, $this->source, $this->transactions, $this->clock);
    $this->late = new AddLateWeeklyLines($this->baskets, $this->sync, $lines, $this->clock);
    $this->base = fn (string $order = 'order-1') => $this->baskets->getBySourceOrder($order);
    $this->made = function (array $lines, string $order = 'order-1') {
        ($this->create)(sourceOf($lines, $order));

        return ($this->base)($order);
    };
});

it('creates one uncharged base per source order with ordered and allocated snapshots, and a replay keeps the first (R18, R22, S9)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4'), sourceLine('beef', '1', '1.500')]);
    ($this->create)(sourceOf([sourceLine('bread', '5', '3')]));

    $lines = $basket->activeLines();
    expect($basket->state)->toBe(BasketState::Draft)
        ->and($basket->kind)->toBe(BasketKind::Weekly)
        ->and($basket->referenceCycleId)->toBe('cycle')
        ->and($basket->validatedTotalCents)->toBeNull()
        ->and(array_map(fn ($line) => [$line->orderedQuantity->toString(), $line->allocatedQuantity->toString(), $line->quantity->toString()], $lines))
        ->toBe([['5.000', '4.000', '4.000'], ['1.000', '1.500', '1.500']])
        ->and($this->baskets->list())->toHaveCount(1)
        ->and($this->wallet->charges)->toBe([]);
});

it('has nothing to collect when no allocation is positive, and Sync revives it when one becomes positive (R24, S20, S30, S88)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '0')]);
    expect($basket->state)->toBe(BasketState::NothingToCollect);

    ($this->sync)(sourceOf([sourceLine('bread', '2', '1')]));

    $revived = ($this->base)();
    expect($revived->state)->toBe(BasketState::Draft)
        ->and($revived->activeLines()[0]->quantity->toString())->toBe('1.000');
});

it('lets staff set the actual quantity, zero included, keeping the order and allocation snapshots (R19, S14)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4'), sourceLine('beef', '1', '1')]);

    ($this->setActual)($this->staff, $basket->id, 'bread', '3');
    ($this->setActual)($this->staff, $basket->id, 'beef', '0');

    $lines = ($this->base)()->activeLines();
    expect($lines[0]->quantity->toString())->toBe('3.000')
        ->and($lines[0]->allocatedQuantity->toString())->toBe('4.000')
        ->and($lines[0]->orderedQuantity->toString())->toBe('5.000')
        ->and($lines[1]->quantity->toString())->toBe('0.000')
        ->and($this->source->calls)->toBe(['lock:cycle', 'lock:cycle']);
});

it('rejects a negative, fractional-for-whole-unit or unknown actual quantity and any change after validation (R9, R19)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4')]);

    expect(fn () => ($this->setActual)($this->staff, $basket->id, 'bread', '-1'))->toThrow(InvalidBasketQuantity::class)
        ->and(fn () => ($this->setActual)($this->staff, $basket->id, 'bread', '1.5'))->toThrow(InvalidBasketQuantity::class)
        ->and(fn () => ($this->setActual)($this->staff, $basket->id, 'honey', '1'))->toThrow(InvalidBasketQuantity::class)
        ->and(($this->base)()->activeLines()[0]->quantity->toString())->toBe('4.000');

    ($this->validate)($this->staff, $basket->id);
    expect(fn () => ($this->setActual)($this->staff, $basket->id, 'bread', '1'))->toThrow(BasketNotEditable::class);
});

it('keeps actual quantities to Finance and Manager (R19a)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4')]);

    expect(fn () => ($this->setActual)(Actor::memberOf('anna', 'household'), $basket->id, 'bread', '1'))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->setActual)(Actor::producer('producer', 'baker'), $basket->id, 'bread', '1'))->toThrow(AuthorizationException::class);
});

it('charges the actual quantities once under the cycle lock, rounds each line half up and attempts closure (R22, R23, S15, S53)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4'), sourceLine('beef', '1', '1.123')]);
    ($this->setActual)($this->staff, $basket->id, 'bread', '3');

    ($this->validate)($this->staff, $basket->id);

    // 3 x 3.50 = 10.50 and 1.123 kg x 20.00 = 22.46.
    expect($this->wallet->charges)->toBe([[$basket->id, 3296]])
        ->and(($this->base)()->state)->toBe(BasketState::Validated)
        ->and(($this->base)()->validatedTotalCents)->toBe(3296)
        ->and($this->source->calls)->toBe(['lock:cycle', 'lock:cycle', 'close:cycle'])
        ->and($this->stock->sales)->toBe(0)
        ->and(fn () => ($this->validate)($this->staff, $basket->id))->toThrow(BasketAlreadyValidated::class)
        ->and($this->wallet->charges)->toHaveCount(1);
});

it('charges the 1.120 kg actual of a 1 kg variable order once, keeping both quantities and the snapshot (R19, R22, S78)', function () {
    $basket = ($this->made)([sourceLine('beef', '1', '1')]);
    ($this->setActual)($this->staff, $basket->id, 'beef', '1.120');

    ($this->validate)($this->staff, $basket->id);

    $line = ($this->base)()->activeLines()[0];
    expect($this->wallet->charges)->toBe([[$basket->id, 2240]])
        ->and([$line->orderedQuantity->toString(), $line->allocatedQuantity->toString(), $line->quantity->toString(), $line->name, $line->priceCents])->toBe(['1.000', '1.000', '1.120', 'Beef', 2000]);
});

it('validates real contents with a zero total without any money movement and refuses a nothing-to-collect base (R22, S88)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '2')]);
    ($this->setActual)($this->staff, $basket->id, 'bread', '0');
    ($this->validate)($this->staff, $basket->id);
    $empty = ($this->made)([sourceLine('bread', '2', '0')], 'order-2');

    expect($this->wallet->charges)->toBe([])
        ->and(($this->base)()->state)->toBe(BasketState::Validated)
        ->and(($this->base)()->validatedTotalCents)->toBe(0)
        ->and(fn () => ($this->validate)($this->staff, $empty->id))->toThrow(BasketNotEditable::class);
});

it('does not lock or close any cycle for an independent stock basket (R19b)', function () {
    $stock = new Basket('stock-1', 'household', 'Household K', BasketState::Draft, 'manager', $this->clock->now(),
        [new BasketLine('l1', 'stock-1', 'oil', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $this->clock->now())]);
    $this->baskets->save($stock);

    ($this->validate)($this->staff, 'stock-1');

    expect($this->source->calls)->toBe([])
        ->and($this->stock->sales)->toBe(1);
});

it('follows a changed allocation by resetting only that line, adds new source lines and archives vanished ones (R18, S29, S39)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4'), sourceLine('beef', '1', '1')]);
    ($this->setActual)($this->staff, $basket->id, 'beef', '0.800');
    ($this->setActual)($this->staff, $basket->id, 'bread', '2');

    ($this->sync)(sourceOf([sourceLine('bread', '5', '3'), sourceLine('beef', '1', '1')]));

    $lines = ($this->base)()->activeLines();
    expect(array_map(fn ($line) => [$line->productId, $line->quantity->toString(), $line->allocatedQuantity->toString()], $lines))
        ->toBe([['bread', '3.000', '3.000'], ['beef', '0.800', '1.000']]);

    ($this->sync)(sourceOf([sourceLine('beef', '1', '1')]));
    expect(array_map(fn ($line) => $line->productId, ($this->base)()->activeLines()))->toBe(['beef'])
        ->and(($this->base)()->lines)->toHaveCount(2);
});

it('leaves a validated base alone on Sync (R18, R23)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4')]);
    ($this->validate)($this->staff, $basket->id);

    ($this->sync)(sourceOf([sourceLine('bread', '5', '1')]));

    expect(($this->base)()->activeLines()[0]->quantity->toString())->toBe('4.000')
        ->and(($this->base)()->state)->toBe(BasketState::Validated);
});

it('revives an unvalidated base on a late arrival, adding the late line to the base itself (R24a, S30)', function () {
    ($this->made)([sourceLine('bread', '2', '0'), sourceLine('beef', '1', '0')]);

    ($this->late)(sourceOf([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '0')]), ['bread']);

    expect(($this->base)()->state)->toBe(BasketState::Draft)
        ->and($this->baskets->list())->toHaveCount(1)
        ->and(array_map(fn ($line) => $line->quantity->toString(), ($this->base)()->activeLines()))->toBe(['2.000', '0.000']);
});

it('gives a validated base a late weekly basket from the same source order with only the late lines, leaving the base untouched (R18, R19, S30)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '0')]);
    ($this->validate)($this->staff, $basket->id);
    $validated = ($this->base)();

    ($this->late)(sourceOf([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '1.500')]), ['beef']);

    $late = $this->baskets->getOpenLateBySourceOrder('order-1');
    expect($this->baskets->list())->toHaveCount(2)
        ->and($late->late)->toBeTrue()
        ->and($late->kind)->toBe(BasketKind::Weekly)
        ->and($late->state)->toBe(BasketState::Draft)
        ->and($late->sourceOrderId)->toBe('order-1')
        ->and($late->referenceCycleId)->toBe('cycle')
        ->and(array_map(fn ($line) => [$line->productId, $line->orderedQuantity->toString(), $line->allocatedQuantity->toString(), $line->quantity->toString()], $late->activeLines()))
        ->toBe([['beef', '1.000', '1.500', '1.500']])
        ->and(($this->base)())->toEqual($validated)
        ->and($this->wallet->charges)->toBe([[$basket->id, 700]]);
});

it('adds later late arrivals to the open late draft, and validates and charges it separately (R19, R22, S91)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '0')]);
    ($this->validate)($this->staff, $basket->id);
    $both = fn (string $beef) => sourceOf([sourceLine('bread', '2', '2'), sourceLine('beef', '1', $beef)]);

    ($this->late)($both('1'), ['beef']);
    $late = $this->baskets->getOpenLateBySourceOrder('order-1');
    ($this->late)($both('1.500'), ['beef']);
    ($this->late)(sourceOf([sourceLine('bread', '2', '3'), sourceLine('beef', '1', '1.500')]), ['bread']);

    $open = $this->baskets->getOpenLateBySourceOrder('order-1');
    expect($this->baskets->list())->toHaveCount(2)
        ->and($open->id)->toBe($late->id)
        ->and(array_map(fn ($line) => [$line->productId, $line->quantity->toString()], $open->activeLines()))->toBe([['beef', '1.500'], ['bread', '3.000']]);

    ($this->validate)($this->staff, $open->id);
    expect($this->wallet->charges)->toBe([[$basket->id, 700], [$open->id, 1050 + 3000]])
        ->and($this->baskets->getOpenLateBySourceOrder('order-1'))->toBeNull();

    ($this->late)($both('2'), ['beef']);
    expect($this->baskets->list())->toHaveCount(3);
});

it('follows a correction of a late line down to zero and back, in the same late basket (R16, R18, R19, S91)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '0')]);
    ($this->validate)($this->staff, $basket->id);
    $with = fn (string $beef) => sourceOf([sourceLine('bread', '2', '2'), sourceLine('beef', '1', $beef)]);
    ($this->late)($with('1'), ['beef']);
    $late = $this->baskets->getOpenLateBySourceOrder('order-1');

    ($this->late)($with('0'), ['beef']);
    $zeroed = $this->baskets->getOpenLateBySourceOrder('order-1');
    expect($zeroed->id)->toBe($late->id)
        ->and($zeroed->state)->toBe(BasketState::NothingToCollect)
        ->and(array_map(fn ($line) => [$line->quantity->toString(), $line->allocatedQuantity->toString()], $zeroed->activeLines()))->toBe([['0.000', '0.000']]);

    ($this->late)($with('1.500'), ['beef']);
    $back = $this->baskets->getOpenLateBySourceOrder('order-1');
    expect($back->id)->toBe($late->id)
        ->and($back->state)->toBe(BasketState::Draft)
        ->and($back->activeLines()[0]->quantity->toString())->toBe('1.500')
        ->and($this->baskets->list())->toHaveCount(2);
});

it('creates no late basket when the late product has nothing allocated to the household (R19)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '0')]);
    ($this->validate)($this->staff, $basket->id);

    ($this->late)(sourceOf([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '0')]), ['beef']);

    expect($this->baskets->list())->toHaveCount(1);
});

it('refuses a second base or a second open late draft for one source order (R19)', function () {
    $basket = ($this->made)([sourceLine('bread', '2', '2')]);
    ($this->validate)($this->staff, $basket->id);
    ($this->late)(sourceOf([sourceLine('bread', '2', '2'), sourceLine('beef', '1', '1')]), ['beef']);
    $duplicate = fn (bool $late) => new Basket('duplicate', 'household', 'Household K', BasketState::Draft, null, $this->clock->now(), [],
        kind: BasketKind::Weekly, sourceOrderId: 'order-1', referenceCycleId: 'cycle', late: $late);

    expect(fn () => $this->baskets->save($duplicate(true)))->toThrow(DomainException::class)
        ->and(fn () => $this->baskets->save($duplicate(false)))->toThrow(DomainException::class);
});

it('never cancels a weekly base (R19b)', function () {
    $basket = ($this->made)([sourceLine('bread', '5', '4')]);

    expect(fn () => (new CancelDraftBasket($this->baskets, $this->transactions, $this->clock))($this->staff, $basket->id))->toThrow(BasketNotEditable::class);
});

it('reads the cycle states with effective quantities and the household cycles still to validate or collect (R35a)', function () {
    $draft = ($this->made)([sourceLine('beef', '1', '1')]);
    ($this->made)([sourceLine('bread', '1', '0')], 'order-2');
    ($this->create)(sourceOf([sourceLine('bread', '1', '1')], 'order-3', 'other'));
    ($this->setActual)($this->staff, $draft->id, 'beef', '0.800');

    $states = (new ReadWeeklyBasketStates($this->baskets))('cycle');
    $pending = new ReadPendingWeeklyCycles($this->baskets);

    expect(array_map(fn ($state) => [$state->state, $state->effective], $states))
        ->toBe([['draft', ['beef' => 800]], ['nothing_to_collect', ['bread' => 0]], ['draft', ['bread' => 1000]]])
        ->and($pending('household'))->toBe(['cycle'])
        ->and($pending('nobody'))->toBe([]);
});
