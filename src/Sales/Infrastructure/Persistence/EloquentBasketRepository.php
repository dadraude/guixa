<?php

namespace Guixa\Sales\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketAdjustmentLine;
use Guixa\Sales\Domain\BasketCollectionEvent;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnexpectedValueException;

final class EloquentBasketRepository implements BasketRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function save(Basket $basket): void
    {
        DB::transaction(fn () => $this->persist($basket));
    }

    private function persist(Basket $basket): void
    {
        $stored = BasketModel::query()->whereKey($basket->id)->lockForUpdate()->first();
        if ($stored !== null && in_array($stored->state, ['validated', 'collected', 'cancelled'], true)) {
            $this->assertProtectedSnapshot($this->toDomain($stored), $basket);
        }

        BasketModel::query()->updateOrCreate(['id' => $basket->id], [
            'household_id' => $basket->householdId,
            'household_name' => $basket->householdName,
            'kind' => $basket->kind,
            'source_order_id' => $basket->sourceOrderId,
            'reference_cycle_id' => $basket->referenceCycleId,
            'late' => $basket->late,
            'state' => $basket->state,
            'created_by' => $basket->createdBy,
            'created_at' => $basket->createdAt->format(DATE_ATOM),
            'validated_by' => $basket->validatedBy,
            'validated_at' => $basket->validatedAt?->format(DATE_ATOM),
            'validated_total_cents' => $basket->validatedTotalCents,
            'collected_by' => $basket->collectedBy,
            'collected_at' => $basket->collectedAt?->format(DATE_ATOM),
            'cancelled_by' => $basket->cancelledBy,
            'cancelled_at' => $basket->cancelledAt?->format(DATE_ATOM),
        ]);

        foreach ($basket->lines as $line) {
            BasketLineModel::query()->updateOrCreate(['id' => $line->id], [
                'basket_id' => $basket->id,
                'kind' => $line->kind,
                'product_id' => $line->productId,
                'return_item_id' => $line->returnItemId,
                'source_order_line_id' => $line->sourceOrderLineId,
                'ordered_quantity' => $line->orderedQuantity?->toString(),
                'allocated_quantity' => $line->allocatedQuantity?->toString(),
                'name' => $line->name,
                'unit' => $line->unit,
                'price_cents' => $line->priceCents,
                'variable' => $line->variable,
                'containers_per_unit' => $line->containersPerUnit,
                'quantity' => $line->quantity->toString(),
                'created_at' => $line->createdAt->format(DATE_ATOM),
                'removed_at' => $line->removedAt?->format(DATE_ATOM),
            ]);
        }

        foreach ($basket->adjustments as $adjustment) {
            $row = BasketAdjustmentModel::query()->firstOrCreate(['id' => $adjustment->id], [
                'basket_id' => $basket->id,
                'amount_cents' => $adjustment->amountCents,
                'reason' => $adjustment->reason,
                'recorded_by' => $adjustment->recordedBy,
                'occurred_at' => $adjustment->occurredAt->format(DATE_ATOM),
            ]);
            if (! $row->wasRecentlyCreated) {
                continue;
            }
            foreach ($adjustment->lines as $line) {
                BasketAdjustmentLineModel::query()->create(['adjustment_id' => $adjustment->id,
                    'product_id' => $line->productId,
                    'quantity_delta' => $line->quantityDelta()]);
            }
        }

        $nextCollectionSequence = ((int) BasketCollectionEventModel::query()->where('basket_id', $basket->id)->max('sequence')) + 1;
        if ($basket->collectedAt !== null && $nextCollectionSequence === 1
            && $basket->collectionEvents === []) {
            BasketCollectionEventModel::query()->create([
                'id' => $this->nextId(), 'basket_id' => $basket->id, 'type' => 'collected',
                'recorded_by' => $basket->collectedBy, 'occurred_at' => $basket->collectedAt->format(DATE_ATOM),
                'sequence' => $nextCollectionSequence++,
            ]);
        }
        foreach ($basket->collectionEvents as $event) {
            $existing = BasketCollectionEventModel::query()->find($event->id);
            if ($existing !== null) {
                if ($existing->basket_id !== $event->basketId || $existing->type !== $event->type
                    || $existing->recorded_by !== $event->recordedBy || $existing->reason !== $event->reason
                    || $this->instant(new DateTimeImmutable($existing->occurred_at)) !== $this->instant($event->occurredAt)) {
                    throw BasketNotEditable::create();
                }

                continue;
            }
            BasketCollectionEventModel::query()->create([
                'id' => $event->id, 'basket_id' => $basket->id, 'type' => $event->type,
                'recorded_by' => $event->recordedBy, 'occurred_at' => $event->occurredAt->format(DATE_ATOM),
                'reason' => $event->reason,
                'sequence' => $nextCollectionSequence++,
            ]);
        }
    }

    private function assertProtectedSnapshot(Basket $stored, Basket $incoming): void
    {
        $collectionTransition = $stored->state === 'validated' && $incoming->state === 'collected'
            && $incoming->collectedAt !== null && $incoming->collectedBy !== null;
        $correctionTransition = $stored->state === 'collected' && $incoming->state === 'validated'
            && count($incoming->collectionEvents) === count($stored->collectionEvents) + 1
            && $this->latestCollectionEventType($incoming) === 'corrected';
        $sameState = $stored->state === $incoming->state;
        $appendedEvents = count($incoming->collectionEvents) - count($stored->collectionEvents);
        $validCollectionEvents = $correctionTransition || ($sameState && $appendedEvents === 0)
            || ($collectionTransition && (($stored->collectedAt === null && $appendedEvents <= 1)
                || ($stored->collectedAt !== null && $appendedEvents === 1))
                && ($appendedEvents === 0 || $this->latestCollectionEventType($incoming) === 'collected'));
        $sameCollection = $stored->collectedBy === $incoming->collectedBy
            && $this->instant($stored->collectedAt) === $this->instant($incoming->collectedAt);

        if ((! $sameState && ! $collectionTransition && ! $correctionTransition) || ! $validCollectionEvents
            || (! $sameCollection && ! ($collectionTransition && $stored->collectedAt === null))
            || ! $this->retainsAdjustments($stored, $incoming)
            || ! $this->retainsCollectionEvents($stored, $incoming)
            || $stored->householdId !== $incoming->householdId
            || $stored->householdName !== $incoming->householdName
            || $stored->createdBy !== $incoming->createdBy
            || $this->instant($stored->createdAt) !== $this->instant($incoming->createdAt)
            || $stored->validatedBy !== $incoming->validatedBy
            || $this->instant($stored->validatedAt) !== $this->instant($incoming->validatedAt)
            || $stored->validatedTotalCents !== $incoming->validatedTotalCents
            || $stored->cancelledBy !== $incoming->cancelledBy
            || $this->instant($stored->cancelledAt) !== $this->instant($incoming->cancelledAt)
            || $this->lineSnapshots($stored) !== $this->lineSnapshots($incoming)) {
            throw BasketNotEditable::create();
        }
    }

    private function retainsAdjustments(Basket $stored, Basket $incoming): bool
    {
        if (count($incoming->adjustments) < count($stored->adjustments)) {
            return false;
        }
        foreach ($stored->adjustments as $index => $adjustment) {
            $candidate = $incoming->adjustments[$index];
            if (! $adjustment->matches($candidate)
                || $this->instant($adjustment->occurredAt) !== $this->instant($candidate->occurredAt)) {
                return false;
            }
        }

        return true;
    }

    private function retainsCollectionEvents(Basket $stored, Basket $incoming): bool
    {
        if (count($incoming->collectionEvents) < count($stored->collectionEvents)) {
            return false;
        }
        foreach ($stored->collectionEvents as $index => $event) {
            $candidate = $incoming->collectionEvents[$index];
            if (! $event->matches($candidate)
                || $this->instant($event->occurredAt) !== $this->instant($candidate->occurredAt)) {
                return false;
            }
        }

        return true;
    }

    private function latestCollectionEventType(Basket $basket): ?string
    {
        $type = null;
        foreach ($basket->collectionEvents as $event) {
            $type = $event->type;
        }

        return $type;
    }

    private function instant(?DateTimeImmutable $instant): ?string
    {
        return $instant?->format('Y-m-d H:i:s.uP');
    }

    /** @return array<string, list<string|int|bool|null>> */
    private function lineSnapshots(Basket $basket): array
    {
        $snapshots = [];
        foreach ($basket->lines as $line) {
            $snapshots[$line->id] = [$line->basketId, $line->kind, $line->productId, $line->returnItemId, $line->name, $line->unit,
                $line->priceCents, $line->variable, $line->containersPerUnit,
                $line->quantity->toString(), $this->instant($line->createdAt), $this->instant($line->removedAt),
                $line->sourceOrderLineId, $line->orderedQuantity?->toString(), $line->allocatedQuantity?->toString()];
        }
        ksort($snapshots);

        return $snapshots;
    }

    public function get(string $id): ?Basket
    {
        $model = BasketModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function getBySourceOrder(string $orderId): ?Basket
    {
        $model = BasketModel::query()->where('source_order_id', $orderId)->where('late', false)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function getOpenLateBySourceOrder(string $orderId): ?Basket
    {
        $model = BasketModel::query()->where('source_order_id', $orderId)->where('late', true)->whereIn('state', ['draft', 'nothing_to_collect'])->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function weeklyOfCycle(string $cycleId): array
    {
        return array_values(BasketModel::query()->where('kind', 'weekly')->where('reference_cycle_id', $cycleId)
            ->with(['lines', 'adjustments.lines', 'collectionEvents'])->orderBy('id')->get()
            ->map(fn (BasketModel $model) => $this->toDomain($model))->all());
    }

    public function lock(string $id): ?Basket
    {
        $model = BasketModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(): array
    {
        return array_values(BasketModel::query()->with(['lines', 'adjustments.lines', 'collectionEvents'])->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (BasketModel $model) => $this->toDomain($model))->all());
    }

    public function getForHousehold(string $id, string $householdId): ?Basket
    {
        $model = BasketModel::query()->whereKey($id)->where('household_id', $householdId)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForHousehold(string $householdId): array
    {
        return array_values(BasketModel::query()->where('household_id', $householdId)->with(['lines', 'adjustments.lines', 'collectionEvents'])
            ->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (BasketModel $model) => $this->toDomain($model))->all());
    }

    public function hasProductHistory(string $productId): bool
    {
        return BasketLineModel::query()->where('product_id', $productId)->exists();
    }

    private function toDomain(BasketModel $model): Basket
    {
        $lines = $model->lines
            ->map(fn (BasketLineModel $line) => new BasketLine($line->id, $line->basket_id, $line->product_id,
                $line->name, $line->unit, (int) $line->price_cents, (bool) $line->variable,
                $this->containerSnapshot($line), Quantity::fromString((string) $line->quantity),
                new DateTimeImmutable($line->created_at), $line->removed_at === null ? null : new DateTimeImmutable($line->removed_at),
                $line->kind, $line->return_item_id, $line->source_order_line_id,
                $line->ordered_quantity === null ? null : Quantity::fromString((string) $line->ordered_quantity),
                $line->allocated_quantity === null ? null : Quantity::fromString((string) $line->allocated_quantity)))
            ->all();

        return new Basket($model->id, $model->household_id, $model->household_name, $model->state,
            $model->created_by, new DateTimeImmutable($model->created_at), array_values($lines),
            $model->validated_by, $model->validated_at === null ? null : new DateTimeImmutable($model->validated_at),
            $model->validated_total_cents === null ? null : (int) $model->validated_total_cents,
            $model->collected_by, $model->collected_at === null ? null : new DateTimeImmutable($model->collected_at),
            $model->cancelled_by, $model->cancelled_at === null ? null : new DateTimeImmutable($model->cancelled_at),
            array_values($model->adjustments->map(fn (BasketAdjustmentModel $adjustment) => new BasketAdjustment(
                $adjustment->id, $adjustment->basket_id, (int) $adjustment->amount_cents,
                $adjustment->reason, $adjustment->recorded_by, new DateTimeImmutable($adjustment->occurred_at),
                array_values($adjustment->lines->map(fn (BasketAdjustmentLineModel $line) => BasketAdjustmentLine::fromDecimal(
                    $line->product_id, (string) $line->quantity_delta))->all())))->all()),
            array_values($model->collectionEvents->map(fn (BasketCollectionEventModel $event) => new BasketCollectionEvent(
                $event->id, $event->basket_id, $event->type, $event->recorded_by,
                new DateTimeImmutable($event->occurred_at), $event->reason))->all()),
            $model->kind, $model->source_order_id, $model->reference_cycle_id, (bool) $model->late);
    }

    private function containerSnapshot(BasketLineModel $line): ?string
    {
        $value = $line->getRawOriginal('containers_per_unit');
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new UnexpectedValueException('The basket container snapshot must be an exact decimal string.');
    }
}
