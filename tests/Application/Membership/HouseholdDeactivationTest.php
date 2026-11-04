<?php

use Guixa\Membership\Application\GetHouseholdDeactivationWarning;
use Guixa\Membership\Application\ListHouseholdsToSettle;
use Guixa\Membership\Domain\ValueObject\OpenHouseholdBasket;
use Guixa\Membership\Infrastructure\Sales\SalesHouseholdBaskets;
use Guixa\Membership\Infrastructure\Wallet\WalletHouseholdBalances;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Sales\Application\ReadOpenHouseholdBaskets;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Wallet\Application\ChargeForBasket;
use Guixa\Wallet\Application\ReadHouseholdBalance;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\InMemoryMovementRepository;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\Membership\MembershipWorld;
use Tests\Support\Ordering\OrderingWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

it('warns with the balance and open baskets before manual deactivation (R35a, S58)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $this->world->balances->set($household, -500);
    $this->world->openBaskets->set($household, [new OpenHouseholdBasket('basket-1', 'draft', '2026-10-28')]);

    $warning = ($this->world->getHouseholdDeactivationWarning)($this->world->manager, $household);

    expect($warning->balanceCents)->toBe(-500)
        ->and($warning->openBaskets)->toHaveCount(1)
        ->and($warning->openBaskets[0]->basketId)->toBe('basket-1')
        ->and($this->world->households->get($household)->isActive())->toBeTrue();
});

it('atomically deactivates a household and unlinks every member while preserving settlement data (R35a, S58)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $anna = ($this->world->addMember)($this->world->manager, $household, 'Anna', 'anna@example.org');
    $biel = ($this->world->addMember)($this->world->manager, $household, 'Biel', 'biel@example.org');
    $this->world->balances->set($household, -500);
    $this->world->openBaskets->set($household, [new OpenHouseholdBasket('basket-1', 'draft', '2026-10-28')]);

    ($this->world->deactivateHousehold)($this->world->manager, $household);

    expect($this->world->households->get($household)->isActive())->toBeFalse()
        ->and($this->world->accounts->get($anna)->householdId)->toBeNull()
        ->and($this->world->accounts->get($biel)->householdId)->toBeNull()
        ->and(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);
});

it('lists inactive households until both their balance and baskets are settled (R35a, S43, S58)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $member = ($this->world->addMember)($this->world->manager, $household, 'Anna', 'anna@example.org');
    $this->world->balances->set($household, 1200);
    ($this->world->unlinkMember)($this->world->manager, $member);

    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);
    $this->world->balances->set($household, 0);
    $this->world->openBaskets->set($household, [new OpenHouseholdBasket('basket-1', 'draft', '2026-10-28')]);
    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);
    $this->world->openBaskets->set($household, []);
    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toBe([]);
});

it('drops a reactivated household from the settlement list while its baskets stay open (R35a)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    $this->world->openBaskets->set($household, [new OpenHouseholdBasket('basket-1', 'validated', '2026-10-28')]);
    ($this->world->deactivateHousehold)($this->world->manager, $household);
    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toHaveCount(1);

    ($this->world->reactivateHousehold)($this->world->manager, $household);

    expect(($this->world->listHouseholdsToSettle)($this->world->manager))->toBe([])
        ->and(($this->world->getHouseholdDeactivationWarning)($this->world->manager, $household)->openBaskets)->toHaveCount(1);
});

it('reactivates manually and restricts household state changes to managers (R35a)', function () {
    $household = ($this->world->createHousehold)($this->world->manager, 'H');
    expect(fn () => ($this->world->deactivateHousehold)($this->world->member, $household))->toThrow(AuthorizationException::class);
    ($this->world->deactivateHousehold)($this->world->manager, $household);
    expect(fn () => ($this->world->reactivateHousehold)($this->world->member, $household))->toThrow(AuthorizationException::class);
    ($this->world->reactivateHousehold)($this->world->manager, $household);
    expect($this->world->households->get($household)->isActive())->toBeTrue();
});

it('keeps a real ordered line and basket through deactivation until the basket is collected and the wallet settles (R35a, S58)', function () {
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
    $sales = new InMemoryBasketRepository;
    $when = new DateTimeImmutable('2026-11-06');
    $basketId = 'weekly-basket-1';
    $sales->save(new Basket($basketId, $household, 'H', BasketState::Draft, null, $when, [
        new BasketLine('line-1', $basketId, $productId, 'Bread', 'loaf', 500, false, null, Quantity::fromString('1'), $when, kind: BasketLineKind::Weekly),
    ], kind: BasketKind::Weekly, sourceOrderId: $order->id, referenceCycleId: $cycleId));
    $movements = new InMemoryMovementRepository;
    $receiptId = (new RecordTopUp($movements, $ordering->clock))($membership->manager, $household, 500, 'Opening receipt');
    $correction = new RecordCorrection($movements, $ordering->clock);
    $correction($membership->manager, $household, -1000, 'Opening adjustment', null, $receiptId, 'opening-adjustment');
    $balances = new WalletHouseholdBalances(new ReadHouseholdBalance($movements));
    $baskets = new SalesHouseholdBaskets(new ReadOpenHouseholdBaskets($sales));
    $warning = new GetHouseholdDeactivationWarning($membership->households, $balances, $baskets);
    $toSettle = new ListHouseholdsToSettle($membership->households, $balances, $baskets);

    expect($warning($membership->manager, $household)->balanceCents)->toBe(-500)
        ->and($warning($membership->manager, $household)->openBaskets[0]->basketId)->toBe($basketId);

    ($membership->deactivateHousehold)($membership->manager, $household);

    expect($membership->accounts->get($anna)->householdId)->toBeNull()
        ->and($membership->accounts->get($biel)->householdId)->toBeNull()
        ->and($ordering->orders->ofHousehold($cycleId, $household)->lines()[0]->productId)->toBe($productId)
        ->and($sales->get($basketId)->state)->toBe(BasketState::Draft)
        ->and($toSettle($membership->manager))->toHaveCount(1);
    $unlinkedAnna = new Actor($anna, [], $membership->accounts->get($anna)->householdId);
    expect(fn () => ($ordering->setOrderLine)($unlinkedAnna, $cycleId, $productId, '1'))->toThrow(NoHousehold::class);

    // Sales validates the base (one Wallet charge by basket) and the household later collects it.
    $sales->save($sales->get($basketId)->validated('manager', $when));
    (new ChargeForBasket($movements, $ordering->clock))($ordering->manager, $household, 'basket-1', 500);
    expect($balances->balanceCents($household))->toBe(-1000)
        ->and($toSettle($membership->manager))->toHaveCount(1);

    $sales->save($sales->get($basketId)->collected('manager', $when));
    (new RecordTopUp($movements, $ordering->clock))($ordering->manager, $household, 1000, 'Settlement');
    expect($balances->balanceCents($household))->toBe(0)
        ->and($sales->get($basketId)->state)->toBe(BasketState::Collected)
        ->and($toSettle($membership->manager))->toBe([]);
});
