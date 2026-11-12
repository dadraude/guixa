<?php

use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Domain\OpportunityKind;
use Guixa\Dashboard\Domain\ReceptionWorkRepository;
use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Infrastructure\Ordering\OrderingOrderOpportunities;
use Guixa\Dashboard\Infrastructure\Sales\SalesBasketWork;
use Guixa\Ordering\Application\ReadDashboardOrderOpportunities;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\FakeReceptionProducerNames;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;
use Tests\Support\Ordering\OrderingWorld;

const OPP_HOUSEHOLD = '00000000-0000-7000-8000-0000000000a1';
const OPP_OTHER_HOUSEHOLD = '00000000-0000-7000-8000-0000000000a2';

beforeEach(function () {
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(OPP_HOUSEHOLD, 'Household A');
    $this->world->households->know(OPP_OTHER_HOUSEHOLD, 'Household J');
    foreach ([OrderingWorld::BAKER, OrderingWorld::GROWER] as $producerId) {
        $this->world->rules->activate($producerId, 3, '20:00');
    }
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false);
    $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: false);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);

    $this->dashboard = function (Actor $actor) {
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
        $empty = new class implements ReceptionWorkRepository, SendWorkRepository
        {
            public function pending(): array
            {
                return [];
            }
        };
        $sales = new SalesBasketWork(new ReadDashboardBasketWork(new ReadPendingCollections($baskets = new InMemoryBasketRepository, $source = new InMemoryWeeklyBasketSource), $pickups, $baskets, $source));

        return (new ListDashboard($sales, $empty, $empty, new OrderingOrderOpportunities(new ReadDashboardOrderOpportunities($this->world->cycles, $names, $this->world->clock)), $this->world->clock))($actor);
    };
    $this->kinds = fn (Actor $actor) => array_map(fn ($opportunity) => $opportunity->kind, ($this->dashboard)($actor)->opportunities);
});

it('shows a member the open window as ordering available, even with a saved order, and never as attention (S3, R11)', function () {
    $member = Actor::memberOf('m1', OPP_HOUSEHOLD);
    ($this->world->setOrderLine)($member, $this->cycleId, $this->bread, '2');

    $dashboard = ($this->dashboard)($member);

    expect($dashboard->attention)->toBe([])
        ->and($dashboard->opportunities)->toHaveCount(1)
        ->and($dashboard->opportunities[0]->kind)->toBe(OpportunityKind::MemberOrder->value)
        ->and($dashboard->opportunities[0]->cycleId)->toBe($this->cycleId)
        ->and($dashboard->opportunities[0]->cutoffs)->toBe([['Baker', '2026-11-04 20:00'], ['Grower', '2026-11-04 20:00']]);
});

it('shows the opportunity of the household the member belongs to now, and none once the household link is gone (S2, R1)', function () {
    $moved = Actor::memberOf('m1', OPP_OTHER_HOUSEHOLD);
    $unlinked = new Actor('m1', [Role::Member]);

    expect(($this->kinds)($moved))->toBe([OpportunityKind::MemberOrder->value])
        ->and(($this->kinds)($unlinked))->toBe([]);
});

it('shows a producer only its own sent summary, never a household or another producer, and not before sending (S2, S10, R11)', function () {
    $producer = Actor::producer('p1', OrderingWorld::BAKER);
    expect(($this->kinds)($producer))->toBe([]);

    $member = Actor::memberOf('m1', OPP_HOUSEHOLD);
    foreach ([OrderingWorld::BAKER, OrderingWorld::GROWER] as $producerId) {
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);
    }
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    expect(($this->kinds)($producer))->toBe([])
        ->and(($this->dashboard)($producer)->attention)->toBe([]);

    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $opportunities = ($this->dashboard)($producer)->opportunities;

    expect($opportunities)->toHaveCount(1)
        ->and($opportunities[0]->kind)->toBe(OpportunityKind::ProducerSummary->value)
        ->and($opportunities[0]->cycleId)->toBe($this->cycleId)
        ->and($opportunities[0]->cutoffs)->toBe([])
        ->and(($this->kinds)($member))->toBe([]);
});

it('shows Catalogue and Manager the maintenance opportunity with no pending work, and nobody else (S10, R11)', function () {
    expect(($this->kinds)(new Actor('c1', [Role::Catalogue])))->toBe([OpportunityKind::CatalogueMaintenance->value])
        ->and(($this->dashboard)(new Actor('c1', [Role::Catalogue]))->attention)->toBe([])
        ->and(($this->kinds)($this->world->manager))->toBe([OpportunityKind::CatalogueMaintenance->value])
        ->and(($this->kinds)(new Actor('f1', [Role::Finance])))->toBe([])
        ->and(($this->kinds)(new Actor('r1', [Role::Receiver])))->toBe([]);
});

it('orders opportunities by fixed kind, then date, independently of any pending work (R13)', function () {
    $both = new Actor('x', [Role::Member, Role::Catalogue], householdId: OPP_HOUSEHOLD);

    expect(($this->kinds)($both))->toBe([OpportunityKind::MemberOrder->value, OpportunityKind::CatalogueMaintenance->value]);
});

it('shows a producer nothing once the producer link or the Producer role is gone (R1, R11)', function () {
    foreach ([OrderingWorld::BAKER] as $producerId) {
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, $producerId);
    }

    expect(($this->kinds)(Actor::producer('p1', OrderingWorld::BAKER)))->toBe([OpportunityKind::ProducerSummary->value])
        ->and(($this->kinds)(new Actor('p1', [Role::Producer])))->toBe([])
        ->and(($this->kinds)(new Actor('p1', [], producerId: OrderingWorld::BAKER)))->toBe([]);
});
