<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Illuminate\Support\Facades\DB;

final class EloquentProductAvailabilityRepository implements ProductAvailabilityRepository
{
    public function markUnavailable(string $cycleId, string $productId): void
    {
        DB::table('product_unavailabilities')->insertOrIgnore(['cycle_id' => $cycleId, 'product_id' => $productId]);
    }

    public function markAvailable(string $cycleId, string $productId): void
    {
        DB::table('product_unavailabilities')->where('cycle_id', $cycleId)->where('product_id', $productId)->delete();
    }

    public function unavailableIn(string $cycleId): array
    {
        $ids = DB::table('product_unavailabilities')
            ->where('cycle_id', $cycleId)
            ->orderBy('product_id')
            ->pluck('product_id')
            ->map(fn (mixed $productId) => (string) $productId)
            ->all();

        return array_values($ids);
    }
}
