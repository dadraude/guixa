<?php

use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Infrastructure\Ordering\OrderingOrderOpportunities;
use Guixa\Dashboard\Infrastructure\Ordering\OrderingReceptionWork;
use Guixa\Dashboard\Infrastructure\Sales\SalesBasketWork;
use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ReadDashboardOrderOpportunities;
use Guixa\Ordering\Application\ReadDashboardOrderWork;
use Guixa\Ordering\Application\ReopenProducerOrder;
use Guixa\Ordering\Application\SendableTotals;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\FakeReceptionProducerNames;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;
use Tests\Support\Ordering\OrderingWorld;

const DASHBOARD_HOUSEHOLD_A = '00000000-0000-7000-8000-0000000000a1';
const DASHBOARD_HOUSEHOLD_B = '00000000-0000-7000-8000-0000000000a2';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(DASHBOARD_HOUSEHOLD_A, 'Household A');
    $this->world->households->know(DASHBOARD_HOUSEHOLD_B, 'Household B');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->householdA = Actor::memberOf('00000000-0000-7000-8000-0000000000aa', DASHBOARD_HOUSEHOLD_A);
    $this->householdB = Actor::memberOf('00000000-0000-7000-8000-0000000000ab', DASHBOARD_HOUSEHOLD_B);
    $this->send = function (string $producerId, string $productId) {
        ($this->world->setOrderLine)($this->householdA, $this->cycleId, $productId, '4');
        ($this->world->setOrderLine)($this->householdB, $this->cycleId, $productId, '6');
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, $producerId);
    };

    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals, $this->world->baskets);
    $transactions = new ImmediateTransactionRunner;
    $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders), $this->world->baskets);
    $this->validate = fn (string $producerId) => (new ValidateReception($this->world->cycles, $transactions, $this->world->clock))($this->world->manager, $this->cycleId, $producerId);
    $this->distribute = fn (string $producerId) => (new MarkProducerDistributed($this->world->cycles, $loader, $settlement, $transactions, $this->world->clock))($this->world->manager, $this->cycleId, $producerId);
    $this->reopen = fn (string $producerId) => (new ReopenProducerOrder($this->world->cycles, $transactions))($this->world->manager, $this->cycleId, $producerId);
    $this->setReceived = fn (string $productId, string $received) => ($this->world->setReceivedTotal)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $productId, $received);
    $this->allocate = fn (string $household, string $quantity) => ($this->world->setAllocation)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, $this->bread, $household, $quantity);

    $this->dashboard = function (Actor $actor) use ($loader) {
        $names = new FakeReceptionProducerNames;
        $names->know(OrderingWorld::BAKER, 'Baker');
        $names->know(OrderingWorld::GROWER, 'Grower');
        $pickups = new class implements CyclePickupDates
        {
            public function forCycles(array $cycleIds): array
            {
                return [];
            }
        };
        $sales = new SalesBasketWork(new ReadDashboardBasketWork(new ReadPendingCollections($baskets = new InMemoryBasketRepository, $source = new InMemoryWeeklyBasketSource), $pickups, $baskets, $source));

        return (new ListDashboard($sales, new OrderingReceptionWork(new ReadDashboardOrderWork($this->world->cycles, $loader, $names, $this->world->clock, new SendableTotals($this->world->orders, $this->world->catalog, $this->world->availability), $this->world->notifier)), new class implements SendWorkRepository
        {
            public function pending(): array
            {
                return [];
            }
        }, new OrderingOrderOpportunities(new ReadDashboardOrderOpportunities($this->world->cycles, $names, $this->world->clock))))($actor);
    };
    $this->groups = fn (Actor $actor) => array_map(fn ($group) => [$group->action, $group->count, $group->producerName, $group->allocationDifferenceCount], ($this->dashboard)($actor)->attention);
    $this->receiver = new Actor('receiver', [Role::Receiver]);
    $this->finance = new Actor('finance', [Role::Finance]);
});

/** Everything a reception read could touch: the received totals, every order line and the cycle's producer states. */
function receptionPersistence($test): string
{
    return serialize([
        $test->world->receivedTotals->ofProducer($test->cycleId, OrderingWorld::BAKER),
        $test->world->receivedTotals->ofProducer($test->cycleId, OrderingWorld::GROWER),
        $test->world->orders->ofCycle($test->cycleId),
        $test->world->cycles->get($test->cycleId),
    ]);
}

it('lists an unopened sent order as delivery to check, and persists nothing (S5)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    $before = receptionPersistence($this);

    $attention = ($this->dashboard)($this->receiver)->attention;

    expect($attention)->toHaveCount(1)
        ->and($attention[0]->action)->toBe(ActionKind::CheckDeliveryAndAllocations->value)
        ->and($attention[0]->cycleId)->toBe($this->cycleId)
        ->and($attention[0]->scheduledDate)->toBe('2026-11-07')
        ->and($attention[0]->count)->toBe(1)
        ->and($attention[0]->producerName)->toBe('Baker')
        ->and($this->world->receivedTotals->ofProducer($this->cycleId, OrderingWorld::BAKER))->toBe([])
        ->and(receptionPersistence($this))->toBe($before);
});

it('groups an unopened and a default-balanced unvalidated reception of one cycle as one check group (S5)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->send)(OrderingWorld::GROWER, $this->apples);
    ($this->setReceived)($this->bread, '10');

    expect(($this->groups)($this->finance))->toBe([[ActionKind::CheckDeliveryAndAllocations->value, 2, null, 0]]);
});

it('shows no reception work before any producer order is sent', function () {
    expect(($this->groups)($this->finance))->toBe([]);
});

it('names the allocation blocker of a validated reception whose allocation no longer matches what was received (S6, R5)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->validate)(OrderingWorld::BAKER);
    ($this->allocate)(DASHBOARD_HOUSEHOLD_A, '1');

    expect(($this->groups)($this->finance))->toBe([[ActionKind::ResolveAllocations->value, 1, 'Baker', 1]])
        ->and(($this->groups)($this->receiver))->toBe([[ActionKind::ResolveAllocations->value, 1, 'Baker', 1]]);
});

it('names the blocker on an unvalidated reception, whose action stays checking (S6, R5)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->allocate)(DASHBOARD_HOUSEHOLD_A, '1');

    expect(($this->groups)($this->receiver))->toBe([[ActionKind::CheckDeliveryAndAllocations->value, 1, 'Baker', 1]]);
});

it('does not name a blocker for a received-versus-basket variance alone (S6, R5)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->validate)(OrderingWorld::BAKER);
    $orderId = $this->world->orders->ofHousehold($this->cycleId, DASHBOARD_HOUSEHOLD_A)->id;
    $this->world->baskets->setActual($orderId, $this->bread, '3');
    $this->world->baskets->validate($orderId);

    expect(($this->groups)($this->finance))->toBe([[ActionKind::MarkDistributed->value, 1, 'Baker', 0]]);
});

it('shows a balanced validated reception as mark distributed to Finance and Manager but not to a Receiver (S6, R10)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->validate)(OrderingWorld::BAKER);

    expect(($this->groups)($this->finance))->toBe([[ActionKind::MarkDistributed->value, 1, 'Baker', 0]])
        ->and(($this->groups)(Actor::manager('manager')))->toBe([[ActionKind::MarkDistributed->value, 1, 'Baker', 0]])
        ->and(($this->groups)($this->receiver))->toBe([]);
});

it('shows reception work to neither a member-only nor a Catalogue-only account (R1, R3)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);

    expect(($this->groups)($this->householdA))->toBe([])
        ->and(($this->groups)(new Actor('catalogue', [Role::Catalogue])))->toBe([])
        ->and(($this->groups)(new Actor('producer', [Role::Producer])))->toBe([]);
});

it('drops a distributed reception, and reclassifies a reopened one and one whose received total changed on the next read (S6)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->validate)(OrderingWorld::BAKER);
    ($this->distribute)(OrderingWorld::BAKER);
    expect(($this->groups)($this->finance))->toBe([]);

    ($this->reopen)(OrderingWorld::BAKER);
    expect(($this->groups)($this->finance))->toBe([[ActionKind::MarkDistributed->value, 1, 'Baker', 0]]);

    ($this->setReceived)($this->bread, '8');
    expect(($this->groups)($this->finance))->toBe([[ActionKind::CheckDeliveryAndAllocations->value, 1, 'Baker', 0]]);
});

it('orders reception groups by pickup date and then by stage, ahead of baskets of the same date (R13)', function () {
    ($this->send)(OrderingWorld::BAKER, $this->bread);
    ($this->send)(OrderingWorld::GROWER, $this->apples);
    ($this->validate)(OrderingWorld::BAKER);

    expect(array_map(fn ($group) => $group->key, ($this->dashboard)($this->finance)->attention))->toBe([
        'check_delivery_and_allocations:cycle:'.$this->cycleId,
        'mark_distributed:cycle:'.$this->cycleId,
    ]);
});
