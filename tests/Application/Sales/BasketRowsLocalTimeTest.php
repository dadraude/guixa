<?php

use App\Http\BasketAdjustmentRow;
use App\Http\BasketCollectionEventRow;
use App\Http\BasketRow;
use Guixa\Sales\Application\BasketAdjustmentData;
use Guixa\Sales\Application\BasketCollectionEventData;
use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketState;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\UtcTimestamp;

// Stored instants come back in UTC; the screen shows them in the cooperative's timezone.
it('renders a stored UTC instant across midnight as a Madrid time (adjustments and collection events)', function () {
    config(['guixa.timezone' => 'Europe/Madrid']);
    $stored = UtcTimestamp::fromStorage('2026-11-04 23:30:00');

    $adjustment = BasketAdjustmentRow::from(new BasketAdjustmentData('a', 100, 'Why', 'manager', $stored));
    $event = BasketCollectionEventRow::from(new BasketCollectionEventData('e', 'collected', 'manager', $stored, null));

    expect($adjustment->occurredAt)->toBe('2026-11-05T00:30:00+01:00')
        ->and($event->occurredAt)->toBe('2026-11-05T00:30:00+01:00');
});

it('renders a stored UTC validation and collection time across midnight as a Madrid time (basket)', function () {
    config(['guixa.timezone' => 'Europe/Madrid']);
    $stored = UtcTimestamp::fromStorage('2026-11-04 23:30:00');
    $line = new BasketLine('line', 'b', null, 'Jar', 'jar', 50, false, null, Quantity::fromString('1'), $stored, kind: BasketLineKind::Return, returnItemId: 'jar');
    $basket = (new Basket('b', 'h', 'Household', BasketState::Draft, null, $stored, [$line], kind: BasketKind::Stock))
        ->validated('manager', $stored)->collected('manager', $stored);

    $row = BasketRow::from(BasketData::from($basket), 0);

    expect($row->validatedAt)->toBe('2026-11-05T00:30:00+01:00')
        ->and($row->collectedAt)->toBe('2026-11-05T00:30:00+01:00');
});
