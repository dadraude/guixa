<?php

use Guixa\Ordering\Application\ListPendingPickups;
use Guixa\Ordering\Application\ReadPendingPickups;
use Guixa\Ordering\Application\WeeklySourceData;
use Guixa\Ordering\Application\WeeklySourceLineData;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\FakeSalesBaskets;
use Tests\Support\InMemory\InMemoryCycleRepository;
use Tests\Support\Ordering\OrderingWorld;

/** A weekly base of the household in the cycle, validated or not. */
function pendingBase(FakeSalesBaskets $baskets, string $orderId, string $cycleId, string $householdId, bool $validated = false, string $allocated = '1'): void
{
    $baskets->createWeeklyBase(new WeeklySourceData($orderId, $cycleId, $householdId, [
        new WeeklySourceLineData("line-{$orderId}", 'bread', 'Bread', 'loaf', 300, false, null, '1', $allocated),
    ]));
    if ($validated) {
        $baskets->validate($orderId);
    }
}

it('lists only the cycles where the requested household has a base to validate or collect (R35a, S58)', function () {
    $cycles = new InMemoryCycleRepository;
    foreach (['2026-10-28', '2026-11-04', '2026-11-11', '2026-11-18'] as $index => $date) {
        $cycles->add(Cycle::restore('cycle-'.($index + 1), new DateTimeImmutable($date), []));
    }
    $baskets = new FakeSalesBaskets;
    $household = 'household-h';
    pendingBase($baskets, 'order-1', 'cycle-1', $household);
    pendingBase($baskets, 'order-2', 'cycle-2', $household, validated: true);
    pendingBase($baskets, 'order-3', 'cycle-3', 'other-household');
    pendingBase($baskets, 'order-4', 'cycle-4', $household, allocated: '0');
    $list = new ListPendingPickups(new ReadPendingPickups($cycles, $baskets));
    $world = new OrderingWorld;

    $pending = $list($world->manager, $household);

    // Draft and validated-but-uncollected bases are obligations; another household's and a nothing-to-collect base are not.
    expect(array_map(fn ($pickup) => $pickup->cycleId, $pending))->toBe(['cycle-1', 'cycle-2'])
        ->and($pending[0]->pickupDate)->toBe('2026-10-28')
        ->and(fn () => $list(Actor::memberOf('member', $household), $household))->toThrow(AuthorizationException::class);
});

it('reads pending cycles for another context without an actor, ordered by pickup date (R35a)', function () {
    $cycles = new InMemoryCycleRepository;
    $baskets = new FakeSalesBaskets;
    $household = 'household-h';
    $cycles->add(Cycle::restore('cycle-late', new DateTimeImmutable('2026-11-11'), []));
    $cycles->add(Cycle::restore('cycle-early', new DateTimeImmutable('2026-10-28'), []));
    pendingBase($baskets, 'order-1', 'cycle-late', $household);
    pendingBase($baskets, 'order-2', 'cycle-early', $household);

    $pending = (new ReadPendingPickups($cycles, $baskets))($household);

    expect(array_map(fn ($pickup) => $pickup->cycleId, $pending))->toBe(['cycle-early', 'cycle-late']);
});
