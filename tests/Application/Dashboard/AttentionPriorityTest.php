<?php

use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\OrderOpportunitiesRepository;
use Guixa\Dashboard\Domain\ReceptionWorkRepository;
use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\ReceptionWork;
use Guixa\Dashboard\Domain\ValueObject\SendWork;
use Guixa\Dashboard\Infrastructure\Sales\SalesBasketWork;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;

/**
 * @param  list<SendWork>  $sends
 * @param  list<ReceptionWork>  $receptions
 * @param  array<string, string>  $pickupDates
 */
function priorityDashboard(InMemoryBasketRepository $repository, InMemoryWeeklyBasketSource $source, array $sends, array $receptions, array $pickupDates): ListDashboard
{
    $pickups = new class($pickupDates) implements CyclePickupDates
    {
        public function __construct(private readonly array $dates) {}

        public function forCycles(array $cycleIds): array
        {
            return array_intersect_key($this->dates, array_flip($cycleIds));
        }
    };

    return new ListDashboard(
        new SalesBasketWork(new ReadDashboardBasketWork(new ReadPendingCollections($repository, $source), $pickups, $repository, $source)),
        new class($receptions) implements ReceptionWorkRepository
        {
            public function __construct(private readonly array $work) {}

            public function pending(): array
            {
                return $this->work;
            }
        },
        new class($sends) implements SendWorkRepository
        {
            public function __construct(private readonly array $work) {}

            public function pending(): array
            {
                return $this->work;
            }
        },
        new class implements OrderOpportunitiesRepository
        {
            public function openWindows(): array
            {
                return [];
            }

            public function sentSummaries(string $producerId): array
            {
                return [];
            }
        },
        new FrozenClock('2026-11-12 09:30'),
    );
}

function priorityBasket(InMemoryBasketRepository $repository, InMemoryWeeklyBasketSource $source, string $id, string $cycle, BasketState $state): void
{
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $line = new BasketLine("line-{$id}", $id, 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('1'), $created);
    $source->place("order-{$id}", $cycle);
    $basket = new Basket($id, 'H', 'Household H', BasketState::Draft, 'manager', $created, [$line], kind: BasketKind::Weekly, sourceOrderId: "order-{$id}");
    $repository->save($state === BasketState::Validated ? $basket->validated('manager', new DateTimeImmutable('2026-11-02T10:00:00+00:00')) : $basket);
}

it('orders a same-date send, reception, draft and collection by stage, after an older collection (R13, S9)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    priorityBasket($repository, $source, 'old-collect', 'cycle-old', BasketState::Validated);
    priorityBasket($repository, $source, 'new-collect', 'cycle-new', BasketState::Validated);
    priorityBasket($repository, $source, 'new-draft', 'cycle-new', BasketState::Draft);
    $dashboard = priorityDashboard(
        $repository, $source,
        [new SendWork('cycle-new', 'p1', 'Baker', '2026-11-07')],
        [new ReceptionWork('cycle-new', 'p1', 'Baker', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false)],
        ['cycle-old' => '2026-10-31', 'cycle-new' => '2026-11-07'],
    );

    $groups = $dashboard(Actor::manager('manager'))->attention;

    expect(array_map(fn ($group) => $group->key, $groups))->toBe([
        'collect_basket:cycle:cycle-old',
        'send_order:cycle:cycle-new',
        'check_delivery_and_allocations:cycle:cycle-new',
        'validate_basket:cycle:cycle-new',
        'collect_basket:cycle:cycle-new',
    ]);
});

it('breaks a same-date, same-stage tie by human context before identifiers, so a cycle id never outranks a producer name (R13)', function () {
    $dashboard = priorityDashboard(
        new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [],
        [
            new ReceptionWork('cycle-a', 'p1', 'Zeta farm', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
            new ReceptionWork('cycle-b', 'p2', 'àlex farm', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
            new ReceptionWork('cycle-c', 'p3', 'Baker', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
        ],
        [],
    );

    $groups = $dashboard(Actor::manager('manager'))->attention;

    expect(array_map(fn ($group) => $group->producerName, $groups))->toBe(['àlex farm', 'Baker', 'Zeta farm']);
});

it('lists an actual-deadline-free pending set purely by date and stage, since no pending family carries a deadline (R12)', function () {
    $dashboard = priorityDashboard(
        new InMemoryBasketRepository, new InMemoryWeeklyBasketSource,
        [new SendWork('cycle-late', 'p1', 'Baker', '2026-11-14')],
        [new ReceptionWork('cycle-early', 'p1', 'Baker', '2026-11-07', ActionKind::MarkDistributed, false)],
        [],
    );

    $groups = $dashboard(Actor::manager('manager'))->attention;

    expect(array_map(fn ($group) => $group->action, $groups))->toBe(['mark_distributed', 'send_order']);
});

it('counts one reception record once when two reads report it (R2, R8 dedup by action, type and key)', function () {
    $work = new ReceptionWork('cycle-a', 'p1', 'Baker', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false);
    $dashboard = priorityDashboard(new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [], [$work, $work], []);

    $groups = $dashboard(Actor::manager('manager'))->attention;

    expect($groups)->toHaveCount(1)->and($groups[0]->count)->toBe(1);
});

it('ties two same-date basket groups on their identical displayed context and falls to stable cycle ids, never to display text (R13)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    priorityBasket($repository, $source, 'first', 'cycle-b', BasketState::Validated);
    priorityBasket($repository, $source, 'second', 'cycle-a', BasketState::Validated);
    $dashboard = priorityDashboard($repository, $source, [], [], ['cycle-a' => '2026-11-07', 'cycle-b' => '2026-11-07']);

    $groups = $dashboard(Actor::manager('manager'))->attention;

    expect(array_map(fn ($group) => $group->cycleId, $groups))->toBe(['cycle-a', 'cycle-b']);
});

it('says caught up only for an account with a context and no pending work after a complete read (R14, S10)', function () {
    $dashboard = priorityDashboard(new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [], [], []);

    foreach ([Actor::manager('m'), Actor::memberOf('u', 'H'), new Actor('c', [Role::Catalogue]), new Actor('r', [Role::Receiver]), Actor::producer('p', 'p1')] as $actor) {
        $data = $dashboard($actor);
        expect($data->showCaughtUp)->toBeTrue()->and($data->showNoContext)->toBeFalse();
    }
});

it('explains an account with no household, producer or staff context instead of reporting zero (R14)', function () {
    $dashboard = priorityDashboard(new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [], [], []);

    $data = $dashboard(new Actor('nobody', []));

    expect($data->showNoContext)->toBeTrue()->and($data->showCaughtUp)->toBeFalse();
});

it('never says caught up while any pending group exists (R14)', function () {
    $dashboard = priorityDashboard(new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [new SendWork('c', 'p', 'Baker', '2026-11-07')], [], []);

    expect($dashboard(Actor::manager('m'))->showCaughtUp)->toBeFalse();
});

it('orders reception sub-actions of different producers on one date by producer name, not by sub-action (R13)', function () {
    $dashboard = priorityDashboard(
        new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [],
        [
            new ReceptionWork('cycle-a', 'p1', 'Zeta farm', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
            new ReceptionWork('cycle-a', 'p2', 'Baker', '2026-11-07', ActionKind::MarkDistributed, false),
            new ReceptionWork('cycle-a', 'p3', 'Hort', '2026-11-07', ActionKind::ResolveAllocations, true),
        ],
        [],
    );

    $groups = $dashboard(Actor::manager('manager'))->attention;

    expect(array_map(fn ($group) => [$group->producerName, $group->action], $groups))->toBe([
        ['Baker', 'mark_distributed'],
        ['Hort', 'resolve_allocations'],
        ['Zeta farm', 'check_delivery_and_allocations'],
    ]);
});

it('orders a several-producer group and a single producer named Quintana on the displayed labels, Quintana first (R13)', function () {
    $dashboard = priorityDashboard(
        new InMemoryBasketRepository, new InMemoryWeeklyBasketSource, [],
        [
            new ReceptionWork('cycle-a', 'p1', 'Baker', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
            new ReceptionWork('cycle-a', 'p2', 'Hort', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
            new ReceptionWork('cycle-b', 'p3', 'Quintana', '2026-11-07', ActionKind::CheckDeliveryAndAllocations, false),
        ],
        [],
    );

    $groups = $dashboard(Actor::manager('manager'))->attention;

    // Shown as "Quintana · Recollida del …" and "Recollida del …": the producer-led label sorts first.
    expect(array_map(fn ($group) => $group->producerName, $groups))->toBe(['Quintana', null]);
});

it('orders the undated "Cicle" and "Cap cicle" groups on the displayed words; a producer group is never undated (R13)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    priorityBasket($repository, $source, 'dateless-cycle', 'cycle-x', BasketState::Draft);
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $line = new BasketLine('line-stock', 'stock', 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('1'), $created);
    $repository->save(new Basket('stock', 'H', 'Household H', BasketState::Draft, 'manager', $created, [$line], kind: BasketKind::Stock));
    $dashboard = priorityDashboard($repository, $source, [], [], []);

    $groups = $dashboard(Actor::manager('manager'))->attention;

    // No pickup date is known for the cycle, so the page shows "Cicle"; the stock basket shows "Cap cicle", which sorts before it.
    expect(array_map(fn ($group) => $group->cycleId, $groups))->toBe([null, 'cycle-x']);
});

it('keeps the context words the ordering uses identical to the Catalan the page renders (R13)', function () {
    $ca = json_decode(file_get_contents(base_path('lang/ca.json')), true, flags: JSON_THROW_ON_ERROR);

    expect([mb_strtolower($ca['Pickup :date']), mb_strtolower($ca['Cycle']), mb_strtolower($ca['No cycle'])])
        ->toBe(['recollida del :date', 'cicle', 'cap cicle']);
});
