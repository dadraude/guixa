<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ProducerOrder;
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
            'cancelled_at' => $cycle->cancelledAt()?->format(DATE_ATOM),
            'settled_at' => $cycle->settledAt()?->format(DATE_ATOM),
            'closed_at' => $cycle->closedAt()?->format(DATE_ATOM),
        ]);

        $rows = array_map(fn (ProducerOrder $order) => [
            'cycle_id' => $cycle->id,
            'producer_id' => $order->producerId,
            'participating' => $order->isParticipating(),
            'closes_at' => $order->cutoff()?->format(DATE_ATOM),
            'sent_at' => $order->sentAt()?->format(DATE_ATOM),
            'validated_at' => $order->validatedAt()?->format(DATE_ATOM),
            'distributed_at' => $order->distributedAt()?->format(DATE_ATOM),
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
        $orders = DB::table('cycle_producers')
            ->where('cycle_id', $model->id)
            ->orderBy('producer_id')
            ->get()
            ->map(fn (object $row) => ProducerOrder::restore(
                $row->producer_id,
                (bool) $row->participating,
                $row->closes_at === null ? null : new DateTimeImmutable($row->closes_at),
                $row->sent_at === null ? null : new DateTimeImmutable($row->sent_at),
                $row->validated_at === null ? null : new DateTimeImmutable($row->validated_at),
                $row->distributed_at === null ? null : new DateTimeImmutable($row->distributed_at),
            ))
            ->all();

        return Cycle::restore(
            $model->id,
            new DateTimeImmutable($model->pickup_date),
            array_values($orders),
            $model->cancelled_at === null ? null : new DateTimeImmutable($model->cancelled_at),
            $model->settled_at === null ? null : new DateTimeImmutable($model->settled_at),
            $model->closed_at === null ? null : new DateTimeImmutable($model->closed_at),
        );
    }
}
