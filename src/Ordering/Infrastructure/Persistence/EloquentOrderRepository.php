<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class EloquentOrderRepository implements OrderRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(Order $order): void
    {
        DB::table('orders')->insertOrIgnore([
            'id' => $order->id,
            'cycle_id' => $order->cycleId,
            'household_id' => $order->householdId,
        ]);

        $lines = $order->lines();
        DB::table('order_lines')
            ->where('order_id', $order->id)
            ->whereNotIn('id', array_map(fn (OrderLine $line) => $line->id, $lines))
            ->delete();

        if ($lines === []) {
            return;
        }

        DB::table('order_lines')->upsert(
            array_map(fn (OrderLine $line) => [
                'id' => $line->id,
                'order_id' => $order->id,
                'product_id' => $line->productId,
                'producer_id' => $line->producerId,
                'product_name' => $line->name,
                'unit' => $line->unit,
                'unit_price_cents' => $line->unitPrice->cents,
                'variable' => $line->variable,
                'containers_per_unit' => $line->containersPerUnit?->toString(),
                'ordered_quantity' => $line->ordered->toString(),
                'allocated_quantity' => $line->allocated->toString(),
                'created_at' => $line->createdAt->format('Y-m-d\TH:i:s.uP'),
                'added_by' => $line->addedBy,
            ], $lines),
            ['id'],
            ['product_name', 'unit', 'unit_price_cents', 'variable', 'containers_per_unit', 'ordered_quantity', 'allocated_quantity'],
        );
    }

    public function deleteOfCycle(string $cycleId): void
    {
        DB::table('orders')->where('cycle_id', $cycleId)->delete();
    }

    public function ofHousehold(string $cycleId, string $householdId): ?Order
    {
        $row = DB::table('orders')->where('cycle_id', $cycleId)->where('household_id', $householdId)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function ofCycle(string $cycleId): array
    {
        $rows = array_values(DB::table('orders')->where('cycle_id', $cycleId)->get()->all());

        if ($rows === []) {
            return [];
        }

        $linesByOrder = DB::table('order_lines')
            ->whereIn('order_id', array_map(fn (stdClass $row) => $row->id, $rows))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        return array_map(fn (stdClass $row) => $this->toDomain($row, array_values($linesByOrder->get($row->id, collect())->all())), $rows);
    }

    /** @return array<string, int> */
    public function lineCountsOfHousehold(string $householdId): array
    {
        return DB::table('orders')
            ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
            ->where('orders.household_id', $householdId)
            ->groupBy('orders.cycle_id')
            ->selectRaw('orders.cycle_id, count(*) as line_count')
            ->pluck('line_count', 'cycle_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function lockFor(string $cycleId, string $householdId): Order
    {
        // The unique (cycle, household) index makes a second concurrent insert wait for the first and then do nothing.
        DB::table('orders')->insertOrIgnore(['id' => $this->nextId(), 'cycle_id' => $cycleId, 'household_id' => $householdId]);

        $row = DB::table('orders')->where('cycle_id', $cycleId)->where('household_id', $householdId)->lockForUpdate()->first();

        return $this->toDomain($row);
    }

    public function hasUnsettledLinesOf(string $productId): bool
    {
        return DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->join('cycles', 'cycles.id', '=', 'orders.cycle_id')
            ->where('order_lines.product_id', $productId)
            ->whereNull('cycles.settled_at')
            ->exists();
    }

    /** @param  list<stdClass>|null  $lineRows  the order's lines when already loaded */
    private function toDomain(stdClass $row, ?array $lineRows = null): Order
    {
        $lineRows ??= DB::table('order_lines')
            ->where('order_id', $row->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        $lines = collect($lineRows)
            ->map(fn (stdClass $line) => new OrderLine(
                $line->id,
                $line->product_id,
                $line->producer_id,
                $line->product_name,
                $line->unit,
                Money::ofCents($line->unit_price_cents),
                (bool) $line->variable,
                $line->containers_per_unit === null ? null : Quantity::fromString($line->containers_per_unit),
                Quantity::fromString($line->ordered_quantity),
                Quantity::fromString($line->allocated_quantity),
                new DateTimeImmutable($line->created_at),
                $line->added_by,
            ))
            ->all();

        return Order::restore($row->id, $row->cycle_id, $row->household_id, array_values($lines));
    }
}
