<?php

namespace Guixa\Membership\Infrastructure\Persistence;

use DateTimeImmutable;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Locks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseHouseholdRepository implements HouseholdRepository
{
    public function __construct(private readonly Locks $locks) {}

    public function lockMembershipChanges(): void
    {
        $this->locks->acquire(Locks::MEMBERSHIP_CHANGES);
    }

    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function save(Household $household): void
    {
        $now = Carbon::now();

        DB::table('households')->upsert(
            [[
                'id' => $household->id,
                'name' => $household->name,
                'deactivated_at' => $household->deactivatedAt?->format(DATE_ATOM),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['id'],
            ['name', 'deactivated_at', 'updated_at'],
        );
    }

    public function get(string $id): ?Household
    {
        $row = DB::table('households')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function getMany(array $ids): array
    {
        $households = [];

        foreach (DB::table('households')->whereIn('id', $ids)->get() as $row) {
            $households[$row->id] = $this->hydrate($row);
        }

        return $households;
    }

    public function list(): array
    {
        return array_values(DB::table('households')->orderBy('created_at')->orderBy('id')->get()
            ->map($this->hydrate(...))
            ->all());
    }

    private function hydrate(stdClass $row): Household
    {
        return Household::restore(
            $row->id,
            $row->name,
            $row->deactivated_at === null ? null : new DateTimeImmutable($row->deactivated_at),
        );
    }
}
