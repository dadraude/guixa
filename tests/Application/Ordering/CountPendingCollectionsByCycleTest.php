<?php

use Guixa\Ordering\Application\CountPendingCollectionsByCycle;
use Guixa\Ordering\Application\WeeklySourceData;
use Guixa\Ordering\Application\WeeklySourceLineData;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\FakeSalesBaskets;

function weeklyBase(FakeSalesBaskets $baskets, string $orderId, string $cycleId, string $state): void
{
    $baskets->createWeeklyBase(new WeeklySourceData($orderId, $cycleId, 'household-h', [
        new WeeklySourceLineData("line-{$orderId}", 'bread', 'Bread', 'loaf', 300, false, null, '1', '1'),
    ]));
    if ($state === 'validated') {
        $baskets->validate($orderId);
    }
}

it('counts validated base and late weekly baskets per cycle and leaves drafts and collected ones out (R23a, R24b)', function () {
    $baskets = new FakeSalesBaskets;
    weeklyBase($baskets, 'order-1', 'cycle-1', 'validated');
    weeklyBase($baskets, 'order-2', 'cycle-1', 'validated');
    weeklyBase($baskets, 'order-3', 'cycle-2', 'draft');
    weeklyBase($baskets, 'order-4', 'cycle-3', 'validated');
    $baskets->collect('order-4');
    $baskets->addLateArrival(new WeeklySourceData('order-1', 'cycle-1', 'household-h', [
        new WeeklySourceLineData('line-late', 'cherries', 'Cherries', 'kg', 500, false, null, '1', '1'),
    ]), ['cherries']);
    $baskets->validateLate('order-1');

    $counts = (new CountPendingCollectionsByCycle($baskets))(Actor::manager('manager'));

    expect($counts)->toBe(['cycle-1' => 3]);
    expect(fn () => (new CountPendingCollectionsByCycle($baskets))(new Actor('finance', [Role::Finance])))->toThrow(AuthorizationException::class)
        ->and(fn () => (new CountPendingCollectionsByCycle($baskets))(Actor::memberOf('member', 'household-h')))->toThrow(AuthorizationException::class);
});
