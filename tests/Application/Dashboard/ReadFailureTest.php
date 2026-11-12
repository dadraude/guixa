<?php

use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\Error\ReadUnavailable;
use Guixa\Dashboard\Domain\OrderOpportunitiesRepository;
use Guixa\Dashboard\Domain\ReceptionWorkRepository;
use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Infrastructure\Ordering\OrderingOrderOpportunities;
use Guixa\Dashboard\Infrastructure\Sales\SalesBasketWork;
use Guixa\Ordering\Application\ReadDashboardOrderOpportunities;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Sales\Application\ReadDashboardBasketWork;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\FakeReceptionProducerNames;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;

function emptySources(?BasketWorkRepository $baskets = null, ?ReceptionWorkRepository $reception = null, ?OrderOpportunitiesRepository $opportunities = null): ListDashboard
{
    return new ListDashboard(
        $baskets ?? new class implements BasketWorkRepository
        {
            public function memberCollection(string $householdId): array
            {
                return [];
            }

            public function staffCollection(): array
            {
                return [];
            }

            public function staffDrafts(): array
            {
                return [];
            }
        },
        $reception ?? new class implements ReceptionWorkRepository
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
        $opportunities ?? new class implements OrderOpportunitiesRepository
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

it('stamps a successful read with the clock instant (R15)', function () {
    $dashboard = emptySources()(Actor::manager('manager'));

    expect($dashboard->attention)->toBe([])
        ->and($dashboard->readAt->format(DATE_ATOM))->toBe('2026-11-12T09:30:00+01:00');
});

it('translates a failed Sales database read into ReadUnavailable, so no caught-up result is returned (R15)', function () {
    $baskets = Mockery::mock(BasketRepository::class);
    $baskets->shouldReceive('pendingCollections')->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('connection lost')));
    $pickups = new class implements CyclePickupDates
    {
        public function forCycles(array $cycleIds): array
        {
            return [];
        }
    };
    $adapter = new SalesBasketWork(new ReadDashboardBasketWork(new ReadPendingCollections($baskets, $source = new InMemoryWeeklyBasketSource), $pickups, $baskets, $source));

    expect(fn () => emptySources(baskets: $adapter)(Actor::manager('manager')))->toThrow(ReadUnavailable::class);
});

it('translates a failed Ordering database read into ReadUnavailable at the opportunities adapter (R15)', function () {
    $cycles = Mockery::mock(CycleRepository::class);
    $cycles->shouldReceive('list')->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('connection lost')));
    $opportunities = new OrderingOrderOpportunities(new ReadDashboardOrderOpportunities($cycles, new FakeReceptionProducerNames, new FrozenClock('2026-11-12 09:30')));
    $dashboard = emptySources(opportunities: $opportunities);

    expect(fn () => $dashboard(Actor::memberOf('member', 'household')))->toThrow(ReadUnavailable::class);
});

it('lets an authorization failure from a source pass through untranslated (R15)', function () {
    $denied = new class implements ReceptionWorkRepository
    {
        public function pending(): array
        {
            throw new AuthorizationException;
        }
    };

    expect(fn () => emptySources(reception: $denied)(Actor::manager('manager')))->toThrow(AuthorizationException::class);
});
