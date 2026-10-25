<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\PickupAdjustment;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Ordering\Domain\ReturnLine;
use Guixa\Ordering\Domain\StockLine;
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
        DB::table('orders')->where('id', $order->id)->update([
            'pickup_status' => $order->pickup()->value,
            'pickup_done_at' => $order->pickupDoneAt()?->format('Y-m-d\TH:i:s.uP'),
            'charged_cents' => $order->charged()?->cents,
        ]);

        $this->storeAdjustments($order);
        $this->storeStockLines($order);
        $this->storeReturnLines($order);

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
                'allocated_by_hand' => $line->allocatedByHand,
                'dropped' => $line->dropped,
                'delivered_quantity' => $line->delivered?->toString(),
                'created_at' => $line->createdAt->format('Y-m-d\TH:i:s.uP'),
                'added_by' => $line->addedBy,
            ], $lines),
            ['id'],
            ['product_name', 'unit', 'unit_price_cents', 'variable', 'containers_per_unit', 'ordered_quantity', 'allocated_quantity', 'allocated_by_hand', 'dropped', 'delivered_quantity'],
        );
    }

    private function storeAdjustments(Order $order): void
    {
        foreach ($order->adjustments() as $adjustment) {
            // Existing history is immutable: only newly assigned ids are inserted, never updated or deleted.
            DB::table('order_pickup_adjustments')->insertOrIgnore([
                'id' => $adjustment->id,
                'order_id' => $order->id,
                'product_id' => $adjustment->productId,
                'name' => $adjustment->name,
                'unit' => $adjustment->unit,
                'unit_price_cents' => $adjustment->unitPrice->cents,
                'quantity_delta_thousandths' => $adjustment->quantityDeltaThousandths,
                'amount_cents' => $adjustment->amount->cents,
                'target_quantity' => $adjustment->target->toString(),
                'recorded_at' => $adjustment->recordedAt->format('Y-m-d\TH:i:s.uP'),
                'recorded_by' => $adjustment->recordedBy,
            ]);
        }
    }

    private function storeStockLines(Order $order): void
    {
        $lines = $order->stockLines();
        DB::table('order_stock_lines')
            ->where('order_id', $order->id)
            ->whereNotIn('id', array_map(fn (StockLine $line) => $line->id, $lines))
            ->delete();

        if ($lines === []) {
            return;
        }

        DB::table('order_stock_lines')->upsert(
            array_map(fn (StockLine $line) => [
                'id' => $line->id,
                'order_id' => $order->id,
                'stock_product_id' => $line->stockProductId,
                'name' => $line->name,
                'unit' => $line->unit,
                'unit_price_cents' => $line->unitPrice->cents,
                'variable' => $line->variable,
                'containers_per_unit' => $line->containersPerUnit?->toString(),
                'quantity' => $line->quantity->toString(),
            ], $lines),
            ['id'],
            ['quantity'],
        );
    }

    private function storeReturnLines(Order $order): void
    {
        $lines = $order->returnLines();
        DB::table('order_return_lines')
            ->where('order_id', $order->id)
            ->whereNotIn('id', array_map(fn (ReturnLine $line) => $line->id, $lines))
            ->delete();

        if ($lines === []) {
            return;
        }

        DB::table('order_return_lines')->upsert(
            array_map(fn (ReturnLine $line) => [
                'id' => $line->id,
                'order_id' => $order->id,
                'return_item_id' => $line->returnItemId,
                'name' => $line->name,
                'amount_cents' => $line->amount->cents,
                'quantity' => $line->quantity,
            ], $lines),
            ['id'],
            ['quantity'],
        );
    }

    public function deleteOfCycle(string $cycleId): void
    {
        DB::table('orders')->where('cycle_id', $cycleId)->delete();
    }

    public function markNotDelivered(string $orderId, array $productIds): void
    {
        DB::table('order_lines')
            ->where('order_id', $orderId)
            ->whereIn('product_id', $productIds)
            ->update(['not_delivered' => true]);
    }

    public function notDeliveredProductIds(string $orderId): array
    {
        $rows = DB::table('order_lines')
            ->where('order_id', $orderId)
            ->where('not_delivered', true)
            ->orderBy('product_id')
            ->get(['product_id'])
            ->all();

        return array_map(fn (stdClass $row): string => $row->product_id, array_values($rows));
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

        $orderIds = array_map(fn (stdClass $row) => $row->id, $rows);
        $stockByOrder = DB::table('order_stock_lines')->whereIn('order_id', $orderIds)->orderBy('id')->get()->groupBy('order_id');
        $returnsByOrder = DB::table('order_return_lines')->whereIn('order_id', $orderIds)->orderBy('id')->get()->groupBy('order_id');
        $adjustmentsByOrder = DB::table('order_pickup_adjustments')->whereIn('order_id', $orderIds)->orderBy('recorded_at')->orderBy('id')->get()->groupBy('order_id');

        return array_map(fn (stdClass $row) => $this->toDomain(
            $row,
            array_values($linesByOrder->get($row->id, collect())->all()),
            array_values($stockByOrder->get($row->id, collect())->all()),
            array_values($returnsByOrder->get($row->id, collect())->all()),
            array_values($adjustmentsByOrder->get($row->id, collect())->all()),
        ), $rows);
    }

    /** @return array<string, int> */
    public function lineCountsOfHousehold(string $householdId): array
    {
        return DB::table('orders')
            ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
            ->where('orders.household_id', $householdId)
            ->where('order_lines.ordered_quantity', '>', 0)
            ->groupBy('orders.cycle_id')
            ->selectRaw('orders.cycle_id, count(*) as line_count')
            ->pluck('line_count', 'cycle_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function pendingPickupCycleIdsOfHousehold(string $householdId): array
    {
        return array_values(DB::table('orders')
            ->where('household_id', $householdId)
            ->where('pickup_status', PickupStatus::Pending->value)
            ->pluck('cycle_id')
            ->map(fn ($cycleId): string => (string) $cycleId)
            ->all());
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

    /**
     * @param  list<stdClass>|null  $lineRows  the order's lines when already loaded
     * @param  list<stdClass>|null  $stockRows  the order's stock lines when already loaded
     * @param  list<stdClass>|null  $returnRows  the order's return lines when already loaded
     * @param  list<stdClass>|null  $adjustmentRows  append-only adjustment history when already loaded
     */
    private function toDomain(stdClass $row, ?array $lineRows = null, ?array $stockRows = null, ?array $returnRows = null, ?array $adjustmentRows = null): Order
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
                (bool) $line->allocated_by_hand,
                (bool) $line->dropped,
                $line->delivered_quantity === null ? null : Quantity::fromString($line->delivered_quantity),
            ))
            ->all();

        return Order::restore(
            $row->id,
            $row->cycle_id,
            $row->household_id,
            array_values($lines),
            PickupStatus::from($row->pickup_status),
            $this->stockLines($stockRows ?? $this->rowsOf('order_stock_lines', $row->id)),
            $this->returnLines($returnRows ?? $this->rowsOf('order_return_lines', $row->id)),
            $row->pickup_done_at === null ? null : new DateTimeImmutable($row->pickup_done_at),
            $row->charged_cents === null ? null : Money::ofCents($row->charged_cents),
            $this->adjustments($adjustmentRows ?? array_values(DB::table('order_pickup_adjustments')->where('order_id', $row->id)->orderBy('recorded_at')->orderBy('id')->get()->all())),
        );
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<PickupAdjustment>
     */
    private function adjustments(array $rows): array
    {
        return array_map(fn (stdClass $row) => new PickupAdjustment(
            $row->id, $row->product_id, $row->name, $row->unit, Money::ofCents($row->unit_price_cents),
            (int) $row->quantity_delta_thousandths, Money::ofCents($row->amount_cents),
            Quantity::fromString($row->target_quantity), new DateTimeImmutable($row->recorded_at), $row->recorded_by,
        ), $rows);
    }

    /** @return list<stdClass> */
    private function rowsOf(string $table, string $orderId): array
    {
        return array_values(DB::table($table)->where('order_id', $orderId)->orderBy('id')->get()->all());
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<StockLine>
     */
    private function stockLines(array $rows): array
    {
        return array_map(fn (stdClass $line) => new StockLine(
            $line->id,
            $line->stock_product_id,
            $line->name,
            $line->unit,
            Money::ofCents($line->unit_price_cents),
            (bool) $line->variable,
            $line->containers_per_unit === null ? null : Quantity::fromString($line->containers_per_unit),
            Quantity::fromString($line->quantity),
        ), $rows);
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<ReturnLine>
     */
    private function returnLines(array $rows): array
    {
        return array_map(
            fn (stdClass $line) => new ReturnLine($line->id, $line->return_item_id, $line->name, Money::ofCents($line->amount_cents), (int) $line->quantity),
            $rows,
        );
    }
}
