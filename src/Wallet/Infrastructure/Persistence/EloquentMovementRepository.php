<?php

namespace Guixa\Wallet\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\PickupAlreadyCharged;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Domain\MovementType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentMovementRepository implements MovementRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(Movement $movement): void
    {
        try {
            // A savepoint inside a surrounding transaction, so a rejected insert leaves it usable.
            DB::transaction(fn () => MovementModel::query()->create([
                'id' => $movement->id,
                'household_id' => $movement->householdId,
                'type' => $movement->type->value,
                'amount_cents' => $movement->amount->cents,
                'occurred_at' => $movement->occurredAt->format(DATE_ATOM),
                'recorded_by' => $movement->recordedBy,
                'note' => $movement->note,
                'cycle_id' => $movement->cycleId,
            ]));
        } catch (UniqueConstraintViolationException $e) {
            if ($movement->isChargeOrCredit() && str_contains($e->getMessage(), 'wallet_movements_one_charge_per_cycle')) {
                throw PickupAlreadyCharged::create();
            }

            throw $e;
        }
    }

    public function balanceOf(string $householdId): Money
    {
        return Money::ofCents((int) MovementModel::query()->where('household_id', $householdId)->sum('amount_cents'));
    }

    public function statementOf(string $householdId): array
    {
        $movements = MovementModel::query()
            ->where('household_id', $householdId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MovementModel $model) => $this->toDomain($model))
            ->all();

        return array_values($movements);
    }

    private function toDomain(MovementModel $model): Movement
    {
        return Movement::restore(
            $model->id,
            $model->household_id,
            MovementType::from($model->type),
            Money::ofCents($model->amount_cents),
            new DateTimeImmutable($model->occurred_at),
            $model->recorded_by,
            $model->note,
            $model->cycle_id,
        );
    }
}
