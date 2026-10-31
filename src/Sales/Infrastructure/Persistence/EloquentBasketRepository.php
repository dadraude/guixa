<?php

namespace Guixa\Sales\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;
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
            'kind' => 'stock',
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
                'kind' => 'stock',
                'product_id' => $line->productId,
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

        if ($basket->collectedAt !== null && ! BasketCollectionEventModel::query()->where('basket_id', $basket->id)->exists()) {
            BasketCollectionEventModel::query()->create([
                'id' => $this->nextId(), 'basket_id' => $basket->id, 'type' => 'collected',
                'recorded_by' => $basket->collectedBy, 'occurred_at' => $basket->collectedAt->format(DATE_ATOM),
            ]);
        }
    }

    private function assertProtectedSnapshot(Basket $stored, Basket $incoming): void
    {
        $collectionTransition = $stored->state === 'validated' && $incoming->state === 'collected'
            && $incoming->collectedAt !== null && $incoming->collectedBy !== null;
        $sameState = $stored->state === $incoming->state;
        $sameCollection = $stored->collectedBy === $incoming->collectedBy
            && $this->instant($stored->collectedAt) === $this->instant($incoming->collectedAt);

        if ((! $sameState && ! $collectionTransition) || ($sameState && ! $sameCollection)
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

    private function instant(?DateTimeImmutable $instant): ?string
    {
        return $instant?->format('Y-m-d H:i:s.uP');
    }

    /** @return array<string, array{string, string, string, string, int, bool, ?string, string, ?string, ?string}> */
    private function lineSnapshots(Basket $basket): array
    {
        $snapshots = [];
        foreach ($basket->lines as $line) {
            $snapshots[$line->id] = [$line->basketId, $line->productId, $line->name, $line->unit,
                $line->priceCents, $line->variable, $line->containersPerUnit,
                $line->quantity->toString(), $this->instant($line->createdAt), $this->instant($line->removedAt)];
        }
        ksort($snapshots);

        return $snapshots;
    }

    public function get(string $id): ?Basket
    {
        $model = BasketModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function lock(string $id): ?Basket
    {
        $model = BasketModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(): array
    {
        return array_values(BasketModel::query()->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (BasketModel $model) => $this->toDomain($model))->all());
    }

    public function hasProductHistory(string $productId): bool
    {
        return BasketLineModel::query()->where('product_id', $productId)->exists();
    }

    private function toDomain(BasketModel $model): Basket
    {
        $lines = BasketLineModel::query()->where('basket_id', $model->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (BasketLineModel $line) => new BasketLine($line->id, $line->basket_id, $line->product_id,
                $line->name, $line->unit, (int) $line->price_cents, (bool) $line->variable,
                $this->containerSnapshot($line), Quantity::fromString((string) $line->quantity),
                new DateTimeImmutable($line->created_at), $line->removed_at === null ? null : new DateTimeImmutable($line->removed_at)))
            ->all();

        return new Basket($model->id, $model->household_id, $model->household_name, $model->state,
            $model->created_by, new DateTimeImmutable($model->created_at), array_values($lines),
            $model->validated_by, $model->validated_at === null ? null : new DateTimeImmutable($model->validated_at),
            $model->validated_total_cents === null ? null : (int) $model->validated_total_cents,
            $model->collected_by, $model->collected_at === null ? null : new DateTimeImmutable($model->collected_at),
            $model->cancelled_by, $model->cancelled_at === null ? null : new DateTimeImmutable($model->cancelled_at));
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
