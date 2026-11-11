<?php

use Guixa\Dashboard\Application\DestinationArea;
use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Infrastructure\Ordering\OrderingReceptionWork;
use Guixa\Dashboard\Infrastructure\Ordering\OrderingSendWork;
use Guixa\Dashboard\Infrastructure\Sales\SalesBasketWork;
use Guixa\Ordering\Application\ReadDashboardOrderWork;
use Guixa\Ordering\Application\SendableTotals;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\FakeReceptionProducerNames;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;
use Tests\Support\Ordering\OrderingWorld;

const SEND_EMPTY = '00000000-0000-7000-8000-0000000000b3';
const SEND_SENT = '00000000-0000-7000-8000-0000000000b4';
const SEND_NONPARTICIPANT = '00000000-0000-7000-8000-0000000000b5';
const SEND_HOUSEHOLD = '00000000-0000-7000-8000-0000000000a1';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(SEND_HOUSEHOLD, 'Household A');
    $this->member = Actor::memberOf('00000000-0000-7000-8000-0000000000aa', SEND_HOUSEHOLD);
    foreach ([SEND_EMPTY, SEND_SENT, SEND_NONPARTICIPANT] as $producerId) {
        $this->world->rules->activate($producerId, 3, '20:00');
        $this->world->notifier->hasAccount($producerId);
    }
    $this->products = [
        OrderingWorld::BAKER => $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false),
        OrderingWorld::GROWER => $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: false),
        SEND_SENT => $this->world->catalog->offer(SEND_SENT, 'Cheese', 'kg', 900, variable: false),
        SEND_NONPARTICIPANT => $this->world->catalog->offer(SEND_NONPARTICIPANT, 'Eggs', 'dozen', 400, variable: false),
    ];
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER, SEND_EMPTY, SEND_SENT, SEND_NONPARTICIPANT]);

    $this->order = fn (string $producerId) => ($this->world->setOrderLine)($this->member, $this->cycleId, $this->products[$producerId], '2');
    $this->close = fn (string $producerId) => ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);

    $this->dashboard = function (Actor $actor) {
        $names = new FakeReceptionProducerNames;
        $names->know(OrderingWorld::BAKER, 'Baker');
        $names->know(OrderingWorld::GROWER, 'Grower');
        $names->know(SEND_SENT, 'Dairy');
        $pickups = new class implements CyclePickupDates
        {
            public function forCycles(array $cycleIds): array
            {
                return [];
            }
        };
        $sales = new SalesBasketWork(new ReadDashboardBasketWork(new ReadPendingCollections($baskets = new InMemoryBasketRepository, $source = new InMemoryWeeklyBasketSource), $pickups, $baskets, $source));
        $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals, $this->world->baskets);
        $read = new ReadDashboardOrderWork(
            $this->world->cycles,
            $loader,
            $names,
            $this->world->clock,
            new SendableTotals($this->world->orders, $this->world->catalog, $this->world->availability),
            $this->world->notifier,
        );

        return (new ListDashboard($sales, new OrderingReceptionWork($read), new OrderingSendWork($read)))($actor);
    };
});

it('shows Manager exactly the one eligible producer among the five cases, linked to the cycle send screen (S8, R9)', function () {
    // Eligible: ordered, closed, with an account. Open: ordered, still open. Empty: closed, nothing ordered.
    // Sent: ordered, closed and sent already. Nonparticipant: ordered, then stopped taking part.
    ($this->order)(OrderingWorld::BAKER);
    ($this->close)(OrderingWorld::BAKER);
    ($this->order)(OrderingWorld::GROWER);
    ($this->close)(SEND_EMPTY);
    ($this->order)(SEND_SENT);
    ($this->close)(SEND_SENT);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, SEND_SENT);
    ($this->order)(SEND_NONPARTICIPANT);
    ($this->close)(SEND_NONPARTICIPANT);
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, SEND_NONPARTICIPANT);

    $sends = array_values(array_filter(($this->dashboard)($this->world->manager)->attention, fn ($group) => $group->action === ActionKind::SendOrder->value));

    expect($sends)->toHaveCount(1)
        ->and($sends[0]->count)->toBe(1)
        ->and($sends[0]->producerName)->toBe('Baker')
        ->and($sends[0]->cycleId)->toBe($this->cycleId)
        ->and($sends[0]->scheduledDate)->toBe('2026-11-07')
        ->and($sends[0]->destination->area)->toBe(DestinationArea::Cycles);
});

function sendGroups(array $attention): array
{
    return array_values(array_filter($attention, fn ($group) => $group->action === ActionKind::SendOrder->value));
}

it('shows Finance, Receiver, member, Catalogue and producer accounts no manual send (R9)', function () {
    ($this->order)(OrderingWorld::BAKER);
    ($this->close)(OrderingWorld::BAKER);

    foreach ([new Actor('finance', [Role::Finance]), new Actor('receiver', [Role::Receiver]), $this->member, new Actor('catalogue', [Role::Catalogue]), new Actor('producer', [Role::Producer])] as $actor) {
        expect(sendGroups(($this->dashboard)($actor)->attention))->toBe([]);
    }
});

it('leaves out a closed, ordered producer whose account cannot be notified, as the send itself refuses it (R9)', function () {
    ($this->order)(OrderingWorld::BAKER);
    ($this->close)(OrderingWorld::BAKER);
    $this->world->notifier->withoutAccount(OrderingWorld::BAKER);

    expect(sendGroups(($this->dashboard)($this->world->manager)->attention))->toBe([]);
});

it('keeps an order whose cutoff has passed as plain send work, with no deadline or overdue claim (R9, R12, S8)', function () {
    ($this->order)(OrderingWorld::BAKER);
    // The baker's cutoff is Wednesday 4 November 20:00; it closes on its own and nothing sends the order.
    $this->world->clock->travelTo('2026-11-05 09:00');

    $sends = sendGroups(($this->dashboard)($this->world->manager)->attention);

    expect($sends)->toHaveCount(1)
        ->and($sends[0]->producerName)->toBe('Baker')
        ->and($sends[0]->scheduledDate)->toBe('2026-11-07')
        ->and(array_keys(get_object_vars($sends[0])))->toBe(['key', 'action', 'cycleId', 'scheduledDate', 'count', 'destination', 'producerName', 'allocationDifferenceCount']);
});

it('drops a sent order from the send list on the next read (S8, R10)', function () {
    ($this->order)(OrderingWorld::BAKER);
    ($this->close)(OrderingWorld::BAKER);
    expect(sendGroups(($this->dashboard)($this->world->manager)->attention))->toHaveCount(1);

    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    expect(sendGroups(($this->dashboard)($this->world->manager)->attention))->toBe([]);
});

it('groups the sendable producers of one cycle under one stable key, counted by (cycle, producer) identity (R9)', function () {
    foreach ([OrderingWorld::BAKER, OrderingWorld::GROWER] as $producerId) {
        ($this->order)($producerId);
        ($this->close)($producerId);
    }

    $sends = sendGroups(($this->dashboard)($this->world->manager)->attention);

    expect($sends)->toHaveCount(1)
        ->and($sends[0]->key)->toBe('send_order:cycle:'.$this->cycleId)
        ->and($sends[0]->count)->toBe(2)
        ->and($sends[0]->producerName)->toBeNull()
        ->and($sends[0]->destination->area)->toBe(DestinationArea::Cycles);
});

it('offers no cycle-closing or send action kind, and a read changes nothing (R10)', function () {
    ($this->order)(OrderingWorld::BAKER);
    ($this->close)(OrderingWorld::BAKER);
    $snapshot = fn () => serialize([$this->world->cycles->get($this->cycleId), $this->world->orders->ofCycle($this->cycleId), $this->world->notifier->notified]);
    $before = $snapshot();

    ($this->dashboard)($this->world->manager);

    expect($snapshot())->toBe($before)
        ->and(array_map(fn (ActionKind $kind) => $kind->value, ActionKind::cases()))->not->toContain('close_cycle')
        ->and($this->world->notifier->notified)->toBe([]);
});

it('lists send work before reception work of the same pickup date (R13)', function () {
    ($this->order)(OrderingWorld::GROWER);
    ($this->close)(OrderingWorld::GROWER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);
    ($this->order)(OrderingWorld::BAKER);
    ($this->close)(OrderingWorld::BAKER);

    expect(array_map(fn ($group) => $group->key, ($this->dashboard)($this->world->manager)->attention))->toBe([
        'send_order:cycle:'.$this->cycleId,
        'check_delivery_and_allocations:cycle:'.$this->cycleId,
    ]);
});
