<?php

use Guixa\Sales\Application\ReadDashboardBasketWork;
use Illuminate\Support\Facades\DB;

it('reads pending collections with the cycle pickup date, for one household or all, without an actor (R4, R8, R14)', function () {
    $week = lateArrivalWeek();
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $pickupDate = (string) DB::table('cycles')->where('id', $week['cycle'])->value('pickup_date');

    $all = app(ReadDashboardBasketWork::class)();
    $mine = app(ReadDashboardBasketWork::class)($week['households']['A']);

    expect(collect($all)->pluck('basketId')->sort()->values()->all())->toBe(collect([$week['baskets']['A'], $week['baskets']['B']])->sort()->values()->all())
        ->and(collect($all)->pluck('cycleId')->unique()->all())->toBe([$week['cycle']])
        ->and(collect($all)->pluck('pickupDate')->unique()->all())->toBe([$pickupDate])
        ->and($mine)->toHaveCount(1)
        ->and($mine[0]->basketId)->toBe($week['baskets']['A']);
});
