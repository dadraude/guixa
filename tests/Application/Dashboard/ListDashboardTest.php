<?php

use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\ReceptionWorkRepository;
use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Infrastructure\Sales\SalesBasketWork;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;

function dashboardWith(InMemoryBasketRepository $repository, InMemoryWeeklyBasketSource $source, array $pickupDates = []): ListDashboard
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
        new class implements ReceptionWorkRepository
        {
            public function pending(): array
            {
                return [];
            }
        },
        new class implements SendWorkRepository
        {
            public function pending(): array
            {
                return [];
            }
        },
    );
}

function validatedBasket(InMemoryBasketRepository $repository, InMemoryWeeklyBasketSource $source, string $id, string $household, ?string $cycle = null, bool $late = false): void
{
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $line = new BasketLine("line-{$id}", $id, 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('1'), $created);
    if ($cycle !== null) {
        $source->place("order-{$id}", $cycle);
    }
    $draft = new Basket($id, $household, "Household {$household}", BasketState::Draft, 'manager', $created, [$line],
        kind: $cycle === null ? BasketKind::Stock : BasketKind::Weekly, sourceOrderId: $cycle === null ? null : "order-{$id}", late: $late);
    $repository->save($draft->validated('manager', new DateTimeImmutable('2026-11-02T10:00:00+00:00')));
}

it('shows a current member the validated closed-cycle basket as one collection group with its cycle date and basket destination (R4, S4)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    validatedBasket($repository, $source, 'closed-basket', 'H', cycle: 'closed-cycle');

    $dashboard = dashboardWith($repository, $source, ['closed-cycle' => '2026-10-28'])(Actor::memberOf('member', 'H'));

    expect($dashboard->attention)->toHaveCount(1);
    $group = $dashboard->attention[0];
    expect($group->action)->toBe(ActionKind::CollectBasket->value)
        ->and($group->count)->toBe(1)
        ->and($group->cycleId)->toBe('closed-cycle')
        ->and($group->scheduledDate)->toBe('2026-10-28')
        ->and($group->destination->staffWide)->toBeFalse()
        ->and($group->destination->basketId)->toBe('closed-basket');
});

function pendingGroups(ListDashboard $list, Actor $actor): array
{
    return array_map(fn ($group) => [$group->cycleId, $group->scheduledDate, $group->count, $group->destination->basketId], $list($actor)->attention);
}

it('groups late, closed-cycle and cycleless baskets by cycle and an explicit no-cycle context; drafts, collected and cancelled weekly work are absent (R4, R14, S4)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    validatedBasket($repository, $source, 'base', 'H', cycle: 'closed-cycle');
    validatedBasket($repository, $source, 'late', 'H', cycle: 'closed-cycle', late: true);
    validatedBasket($repository, $source, 'stock', 'H');
    validatedBasket($repository, $source, 'collected', 'H');
    $repository->save($repository->get('collected')->collected('manager', new DateTimeImmutable('2026-11-03T10:00:00+00:00')));
    // A cancelled cycle's weekly work was cancelled with it: it never becomes a basket to collect.
    $source->place('order-cancelled-weekly', 'cancelled-cycle');
    $line = new BasketLine('line-cw', 'cancelled-weekly', 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('1'), new DateTimeImmutable('2026-10-30T10:00:00+00:00'));
    $draft = new Basket('cancelled-weekly', 'H', 'Household H', BasketState::Draft, 'manager', new DateTimeImmutable('2026-10-30T10:00:00+00:00'), [$line], kind: BasketKind::Weekly, sourceOrderId: 'order-cancelled-weekly');
    $repository->save($draft);
    $repository->save($draft->cancelled('manager', new DateTimeImmutable('2026-11-03T10:00:00+00:00')));

    $groups = pendingGroups(dashboardWith($repository, $source, ['closed-cycle' => '2026-10-28', 'cancelled-cycle' => '2026-11-04']), Actor::memberOf('member', 'H'));

    expect($groups)->toBe([['closed-cycle', '2026-10-28', 2, null], [null, null, 1, 'stock']]);
});

it('shows a member of another household none of H\'s work (S4)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    validatedBasket($repository, $source, 'h-basket', 'H', cycle: 'cycle-1');

    expect(dashboardWith($repository, $source, ['cycle-1' => '2026-11-04'])(Actor::memberOf('j-member', 'J'))->attention)->toBe([])
        ->and(dashboardWith($repository, $source)(new Actor('nobody', []))->attention)->toBe([]);
});

it('counts a Finance member\'s own basket once in the staff-wide group and shows other households\' baskets (S1 portion, R2, R8)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    validatedBasket($repository, $source, 'h-basket', 'H', cycle: 'cycle-1');
    validatedBasket($repository, $source, 'j-basket', 'J', cycle: 'cycle-1');
    $list = dashboardWith($repository, $source, ['cycle-1' => '2026-11-04']);

    $financeMember = new Actor('finance-member', [Role::Finance], 'H');
    $group = $list($financeMember)->attention;
    expect($group)->toHaveCount(1)
        ->and($group[0]->count)->toBe(2)
        ->and($group[0]->destination->staffWide)->toBeTrue()
        ->and($group[0]->destination->basketId)->toBeNull();

    $memberOnly = $list(Actor::memberOf('member', 'H'))->attention;
    expect($memberOnly)->toHaveCount(1)
        ->and($memberOnly[0]->count)->toBe(1)
        ->and($memberOnly[0]->destination->staffWide)->toBeFalse()
        ->and($memberOnly[0]->destination->basketId)->toBe('h-basket');
});

it('orders dated groups by date, then undated groups, with stable keys (R13 primitive)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    validatedBasket($repository, $source, 'stock', 'H');
    validatedBasket($repository, $source, 'later', 'H', cycle: 'cycle-b');
    validatedBasket($repository, $source, 'earlier', 'H', cycle: 'cycle-a');

    $groups = dashboardWith($repository, $source, ['cycle-a' => '2026-11-04', 'cycle-b' => '2026-11-11'])(Actor::manager('manager'))->attention;

    expect(array_map(fn ($group) => $group->key, $groups))->toBe(['collect_basket:cycle:cycle-a', 'collect_basket:cycle:cycle-b', 'collect_basket:no-cycle']);
});

function draftBasket(InMemoryBasketRepository $repository, string $id, int $priceCents = 700, string $quantity = '1', ?string $household = 'H'): Basket
{
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $line = new BasketLine("line-{$id}", $id, 'honey', 'Honey', 'jar', $priceCents, false, null, Quantity::fromString($quantity), $created);
    $draft = new Basket($id, $household, "Household {$household}", BasketState::Draft, 'manager', $created, [$line], kind: BasketKind::Stock);
    $repository->save($draft);

    return $draft;
}

it('shows Finance a zero-price draft with effective goods as validation work, whatever its amount (R7, S7)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    $draft = draftBasket($repository, 'free-honey', priceCents: 0);

    $dashboard = dashboardWith($repository, $source)(new Actor('finance', [Role::Finance]));

    expect($draft->totalCents())->toBe(0)
        ->and($dashboard->attention)->toHaveCount(1);
    $group = $dashboard->attention[0];
    expect($group->action)->toBe(ActionKind::ValidateBasket->value)
        ->and($group->count)->toBe(1)
        ->and($group->cycleId)->toBeNull()
        ->and($group->destination->staffWide)->toBeTrue()
        ->and($group->destination->basketId)->toBe('free-honey');
});

function weeklyDraft(InMemoryBasketRepository $repository, InMemoryWeeklyBasketSource $source, string $id, string $cycle, string $household = 'H', string $actual = '0'): Basket
{
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $source->place("order-{$id}", $cycle);
    $line = new BasketLine("line-{$id}", $id, 'bread', 'Bread', 'loaf', 300, false, null, Quantity::fromString($actual), $created,
        kind: BasketLineKind::Weekly, sourceOrderLineId: "source-{$id}", orderedQuantity: Quantity::fromString('2'), allocatedQuantity: Quantity::fromString('2'));
    $draft = new Basket($id, $household, "Household {$household}", BasketState::Draft, 'manager', $created, [$line], kind: BasketKind::Weekly, sourceOrderId: "order-{$id}");
    $repository->save($draft);

    return $draft;
}

function financeActor(): Actor
{
    return new Actor('finance', [Role::Finance]);
}

it('treats a net-zero draft of goods and a returned item as validation work (R7, S7)', function () {
    $repository = new InMemoryBasketRepository;
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $goods = new BasketLine('goods', 'net-zero', 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('1'), $created);
    $returned = new BasketLine('returned', 'net-zero', null, 'Empty jar', 'jar', 700, false, null, Quantity::fromString('1'), $created, kind: BasketLineKind::Return, returnItemId: 'jar');
    $repository->save(new Basket('net-zero', 'H', 'Household H', BasketState::Draft, 'manager', $created, [$goods, $returned], kind: BasketKind::Stock));

    $groups = dashboardWith($repository, new InMemoryWeeklyBasketSource)(financeActor())->attention;

    expect($repository->get('net-zero')->totalCents())->toBe(0)
        ->and(array_map(fn ($group) => $group->action, $groups))->toBe([ActionKind::ValidateBasket->value]);
});

it('treats a weekly draft whose actual quantity is zero as validation work, since validation accepts it (R7, 001 R19a/R22)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    weeklyDraft($repository, $source, 'zero-actual', 'cycle-1');

    $groups = dashboardWith($repository, $source, ['cycle-1' => '2026-11-04'])(financeActor())->attention;

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->action)->toBe(ActionKind::ValidateBasket->value)
        ->and($groups[0]->cycleId)->toBe('cycle-1')
        ->and($groups[0]->scheduledDate)->toBe('2026-11-04')
        ->and($groups[0]->destination->basketId)->toBe('zero-actual');
});

it('excludes empty retained, cancelled and validated drafts from draft work (R7, S7)', function () {
    $repository = new InMemoryBasketRepository;
    $draft = draftBasket($repository, 'emptied');
    $repository->save($draft->withLines([$draft->lines[0]->removedAt(new DateTimeImmutable('2026-10-31T10:00:00+00:00'))]));
    $cancelled = draftBasket($repository, 'cancelled');
    $repository->save($cancelled->cancelled('manager', new DateTimeImmutable('2026-10-31T10:00:00+00:00')));
    draftBasket($repository, 'validated');
    $repository->save($repository->get('validated')->validated('manager', new DateTimeImmutable('2026-10-31T10:00:00+00:00')));

    $actions = array_map(fn ($group) => $group->action, dashboardWith($repository, new InMemoryWeeklyBasketSource)(financeActor())->attention);

    expect($actions)->toBe([ActionKind::CollectBasket->value]);
});

it('keeps validation and collection as distinct groups and orders validation before collection (R7, S7)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    validatedBasket($repository, $source, 'to-collect', 'H', cycle: 'cycle-1');
    weeklyDraft($repository, $source, 'to-validate', 'cycle-1', household: 'J', actual: '2');

    $groups = dashboardWith($repository, $source, ['cycle-1' => '2026-11-04'])(financeActor())->attention;

    expect(array_map(fn ($group) => [$group->action, $group->cycleId, $group->count], $groups))
        ->toBe([[ActionKind::ValidateBasket->value, 'cycle-1', 1], [ActionKind::CollectBasket->value, 'cycle-1', 1]]);
});

it('groups several drafts by cycle with the staff list as destination and a stock draft under no cycle (R7, R14)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    weeklyDraft($repository, $source, 'draft-a', 'cycle-1', household: 'A', actual: '1');
    weeklyDraft($repository, $source, 'draft-b', 'cycle-1', household: 'B', actual: '1');
    draftBasket($repository, 'stock-draft');

    $groups = dashboardWith($repository, $source, ['cycle-1' => '2026-11-04'])(financeActor())->attention;

    expect(array_map(fn ($group) => [$group->cycleId, $group->count, $group->destination->basketId, $group->destination->collectionList], $groups))
        ->toBe([['cycle-1', 2, null, false], [null, 1, 'stock-draft', false]]);
});

it('shows a Finance member\'s household draft once and gives the draft work to no other audience (R7, S7)', function () {
    $repository = new InMemoryBasketRepository;
    draftBasket($repository, 'h-draft', household: 'H');
    $list = dashboardWith($repository, new InMemoryWeeklyBasketSource);

    $financeMember = $list(new Actor('finance-member', [Role::Finance], 'H'))->attention;
    expect($financeMember)->toHaveCount(1)->and($financeMember[0]->count)->toBe(1);

    foreach ([Actor::memberOf('member', 'H'), new Actor('receiver', [Role::Receiver]), new Actor('catalogue', [Role::Catalogue])] as $actor) {
        expect($list($actor)->attention)->toBe([]);
    }
    expect(array_map(fn ($case) => $case->value, ActionKind::cases()))->not->toContain('close_cycle');
});
