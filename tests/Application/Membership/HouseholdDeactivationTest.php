<?php

use Guixa\Membership\Application\GetHouseholdDeactivationWarning;
use Guixa\Membership\Application\ListHouseholdsToSettle;
use Guixa\Membership\Domain\ValueObject\PendingHouseholdPickup;
use Guixa\Membership\Infrastructure\Ordering\OrderingHouseholdPickups;
use Guixa\Membership\Infrastructure\Wallet\WalletHouseholdBalances;
use Guixa\Ordering\Application\ReadPendingPickups;
use Guixa\Ordering\Application\WeeklySourceData;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Wallet\Application\ChargeForBasket;
use Guixa\Wallet\Application\ReadHouseholdBalance;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\InMemoryMovementRepository;
use Tests\Support\Membership\MembershipWorld;
use Tests\Support\Ordering\OrderingWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

it('warns with the balance and pending pickups before manual deactivation (R35a, S58)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $this->world->balances->set($household, -500);
    $this->world->pickups->set($household, [new PendingHouseholdPickup('cycle-1', '2026-10-28')]);

    $warning = ($this->world->getHouseholdDeactivationWarning)($this->world->manager, $household);

    expect($warning->balanceCents)->toBe(-500)
        ->and($warning->pendingPickups)->toHaveCount(1)
        ->and($warning->pendingPickups[0]->cycleId)->toBe('cycle-1')
        ->and($this->world->households->get($household)->isActive())->toBeTrue();
});

it('atomically deactivates a household and unlinks every member while preserving settlement data (R35a, S58)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $anna = ($this->world->addMember)($this->world->manager, $household, 'Anna', 'anna@example.org');
    $biel = ($this->world->addMember)($this->world->manager, $household, 'Biel', 'biel@example.org');
    $this->world->balances->set($household, -500);
    $this->world->pickups->set($household, [new PendingHouseholdPickup('cycle-1', '2026-10-28')]);

    ($this->world->deactivateHousehold)($this->world->manager, $household);

    expect($this->world->households->get($household)->isActive())->toBeFalse()
        ->and($this->world->accounts->get($anna)->householdId)->toBeNull()
        ->and($this->world->accounts->get($biel)->householdId)->toBeNull()
        ->and(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);
});

it('lists inactive households until both their balance and pickups are settled (R35a, S43, S58)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $member = ($this->world->addMember)($this->world->manager, $household, 'Anna', 'anna@example.org');
    $this->world->balances->set($household, 1200);
    ($this->world->unlinkMember)($this->world->manager, $member);

    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);
    $this->world->balances->set($household, 0);
    $this->world->pickups->set($household, [new PendingHouseholdPickup('cycle-1', '2026-10-28')]);
    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);
    $this->world->pickups->set($household, []);
    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toBe([]);
});

it('reactivates manually and restricts household state changes to managers (R35a)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    expect(fn () => ($this->world->deactivateHousehold)($this->world->member, $household))->toThrow(AuthorizationException::class);
    ($this->world->deactivateHousehold)($this->world->manager, $household);
    expect(fn () => ($this->world->reactivateHousehold)($this->world->member, $household))->toThrow(AuthorizationException::class);
    ($this->world->reactivateHousehold)($this->world->manager, $household);
    expect($this->world->households->get($household)->isActive())->toBeTrue();
});

it('keeps a real ordered line and pickup through deactivation until pickup and wallet settle (R35a, S58)', function () {
    $membership = $this->world;
    $ordering = new OrderingWorld;
    $household = ($membership->createHousehold)($membership->manager, 'H');
    $anna = ($membership->addMember)($membership->manager, $household, 'Anna', 'anna@example.org');
    $biel = ($membership->addMember)($membership->manager, $household, 'Biel', 'biel@example.org');
    $cycleId = '00000000-0000-7000-8000-0000000000c1';
    $productId = '00000000-0000-7000-8000-0000000000f1';
    $ordering->households->know($household, 'H');
    $ordering->cycles->add(Cycle::restore(
        $cycleId,
        new DateTimeImmutable('2026-11-07'),
        [],
        settledAt: new DateTimeImmutable('2026-11-06'),
    ));
    $order = Order::restore(
        '00000000-0000-7000-8000-0000000000d1',
        $cycleId,
        $household,
        [new OrderLine(
            '00000000-0000-7000-8000-0000000000d2', $productId, OrderingWorld::BAKER,
            'Bread', 'loaf', Money::ofCents(500), false, null,
            Quantity::fromString('1'), Quantity::fromString('1'),
            new DateTimeImmutable('2026-10-22'), $anna,
        )],
    );
    $ordering->orders->add($order);
    $ordering->baskets->createWeeklyBase(WeeklySourceData::of($order));
    $movements = new InMemoryMovementRepository;
    $receiptId = (new RecordTopUp($movements, $ordering->clock))($membership->manager, $household, 500, 'Opening receipt');
    $correction = new RecordCorrection($movements, $ordering->clock);
    $correction($membership->manager, $household, -1000, 'Opening adjustment', null, $receiptId, 'opening-adjustment');
    $balances = new WalletHouseholdBalances(new ReadHouseholdBalance($movements));
    $pickups = new OrderingHouseholdPickups(new ReadPendingPickups($ordering->cycles, $ordering->baskets));
    $warning = new GetHouseholdDeactivationWarning($membership->households, $balances, $pickups);
    $toSettle = new ListHouseholdsToSettle($membership->households, $balances, $pickups);

    expect($warning($membership->manager, $household)->balanceCents)->toBe(-500)
        ->and($warning($membership->manager, $household)->pendingPickups[0]->cycleId)->toBe($cycleId);

    ($membership->deactivateHousehold)($membership->manager, $household);

    expect($membership->accounts->get($anna)->householdId)->toBeNull()
        ->and($membership->accounts->get($biel)->householdId)->toBeNull()
        ->and($ordering->orders->ofHousehold($cycleId, $household)->lines()[0]->productId)->toBe($productId)
        ->and($ordering->baskets->stateOfHousehold($cycleId, $household))->toBe('draft')
        ->and($toSettle($membership->manager))->toHaveCount(1);
    $unlinkedAnna = new Actor($anna, [], $membership->accounts->get($anna)->householdId);
    expect(fn () => ($ordering->setOrderLine)($unlinkedAnna, $cycleId, $productId, '1'))->toThrow(NoHousehold::class);

    // Sales validates the base (one Wallet charge by basket) and the household later collects it.
    $ordering->baskets->validate($order->id);
    (new ChargeForBasket($movements, $ordering->clock))($ordering->manager, $household, 'basket-1', 500);
    expect($balances->balanceCents($household))->toBe(-1000)
        ->and($toSettle($membership->manager))->toHaveCount(1);

    $ordering->baskets->collect($order->id);
    (new RecordTopUp($movements, $ordering->clock))($ordering->manager, $household, 1000, 'Settlement');
    expect($balances->balanceCents($household))->toBe(0)
        ->and($ordering->baskets->stateOfHousehold($cycleId, $household))->toBe('collected')
        ->and($toSettle($membership->manager))->toBe([]);
});
