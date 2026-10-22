<?php

namespace Guixa\Catalog\Infrastructure\Persistence;

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;
use Illuminate\Support\Str;

final class EloquentProducerRepository implements ProducerRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function add(Producer $producer): void
    {
        ProducerModel::query()->updateOrCreate(['id' => $producer->id], [
            'name' => $producer->name(),
            'active' => $producer->isActive(),
            'cutoff_days_before' => $producer->cutoffRule()->daysBefore,
            'cutoff_time' => $producer->cutoffRule()->time,
        ]);
    }

    public function get(string $id): ?Producer
    {
        $model = ProducerModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(): array
    {
        $producers = ProducerModel::query()
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (ProducerModel $model) => $this->toDomain($model))
            ->all();

        return array_values($producers);
    }

    private function toDomain(ProducerModel $model): Producer
    {
        return Producer::restore(
            $model->id,
            $model->name,
            $model->active,
            // A `time` column comes back as HH:MM:SS.
            CutoffRule::of($model->cutoff_days_before, substr($model->cutoff_time, 0, 5)),
        );
    }
}
