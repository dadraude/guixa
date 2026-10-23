<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Illuminate\Support\Facades\DB;

final class EloquentCooperativeSettings implements CooperativeSettings
{
    private const ROW = 1;

    public function pickupWeekday(): ?PickupWeekday
    {
        $weekday = DB::table('cooperative_settings')->where('id', self::ROW)->value('pickup_weekday');

        return $weekday === null ? null : PickupWeekday::of((int) $weekday);
    }

    public function changePickupWeekday(PickupWeekday $weekday): void
    {
        DB::table('cooperative_settings')->upsert(
            [['id' => self::ROW, 'pickup_weekday' => $weekday->isoDay]],
            ['id'],
            ['pickup_weekday'],
        );
    }
}
