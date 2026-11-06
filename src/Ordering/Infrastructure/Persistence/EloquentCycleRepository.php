<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentCycleRepository implements CycleRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(Cycle $cycle): void
    {
        CycleModel::query()->updateOrCreate(['id' => $cycle->id], [
            'pickup_date' => $cycle->pickupDate()->format('Y-m-d'),
            'cancelled_at' => UtcTimestamp::toStorage($cycle->cancelledAt()),
            'settled_at' => UtcTimestamp::toStorage($cycle->settledAt()),
            'closed_at' => UtcTimestamp::toStorage($cycle->closedAt()),
        ]);

        $rows = array_map(fn (ProducerOrder $order) => [
            'cycle_id' => $cycle->id,
            'producer_id' => $order->producerId,
            'participating' => $order->isParticipating(),
            'closes_at' => UtcTimestamp::toStorage($order->cutoff()),
            'sent_at' => UtcTimestamp::toStorage($order->sentAt()),
            'validated_at' => UtcTimestamp::toStorage($order->validatedAt()),
            'distributed_at' => UtcTimestamp::toStorage($order->distributedAt()),
        ], $cycle->producerOrders());

        if ($rows !== []) {
            DB::table('cycle_producers')->upsert($rows, ['cycle_id', 'producer_id'], ['participating', 'closes_at', 'sent_at', 'validated_at', 'distributed_at']);
        }
    }

    public function get(string $id): ?Cycle
    {
        $model = CycleModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function getForUpdate(string $id): ?Cycle
    {
        $model = CycleModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function getForShare(string $id): ?Cycle
    {
        $model = CycleModel::query()->whereKey($id)->sharedLock()->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(): array
    {
        $cycles = CycleModel::query()
            ->with('producers')
            ->orderBy('pickup_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CycleModel $model) => $this->toDomain($model))
            ->all();

        return array_values($cycles);
    }

    public function existsOnPickupDate(DateTimeInterface $pickupDate): bool
    {
        return CycleModel::query()
            ->where('pickup_date', $pickupDate->format('Y-m-d'))
            ->whereNull('cancelled_at')
            ->exists();
    }

    private function toDomain(CycleModel $model): Cycle
    {
        $orders = $model->producers
            ->map(fn (object $row) => ProducerOrder::restore(
                $row->producer_id,
                (bool) $row->participating,
                UtcTimestamp::fromStorage($row->closes_at),
                UtcTimestamp::fromStorage($row->sent_at),
                UtcTimestamp::fromStorage($row->validated_at),
                UtcTimestamp::fromStorage($row->distributed_at),
            ))
            ->all();

        return Cycle::restore(
            $model->id,
            new DateTimeImmutable($model->pickup_date),
            array_values($orders),
            UtcTimestamp::fromStorage($model->cancelled_at),
            UtcTimestamp::fromStorage($model->settled_at),
            UtcTimestamp::fromStorage($model->closed_at),
        );
    }
}
