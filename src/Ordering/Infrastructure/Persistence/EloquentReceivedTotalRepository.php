<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\DB;

final class EloquentReceivedTotalRepository implements ReceivedTotalRepository
{
    public function set(ReceivedTotal $total): void
    {
        DB::table('received_totals')->upsert(
            [[
                'cycle_id' => $total->cycleId,
                'producer_id' => $total->producerId,
                'product_id' => $total->productId,
                'received_quantity' => $total->received->toString(),
            ]],
            ['cycle_id', 'product_id'],
            ['producer_id', 'received_quantity'],
        );
    }

    public function hasProductReference(string $productId): bool
    {
        return DB::table('received_totals')->where('product_id', $productId)->exists();
    }

    public function ofProducer(string $cycleId, string $producerId): array
    {
        return DB::table('received_totals')
            ->where('cycle_id', $cycleId)
            ->where('producer_id', $producerId)
            ->pluck('received_quantity', 'product_id')
            ->map(fn (string $received) => Quantity::fromString($received))
            ->all();
    }
}
