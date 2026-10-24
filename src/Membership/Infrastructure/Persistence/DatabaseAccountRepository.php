<?php

namespace Guixa\Membership\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;

/** Accounts are the `users` table; their roles live in `account_roles`. */
final class DatabaseAccountRepository implements AccountRepository
{
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function save(Account $account): void
    {
        DB::table('users')->updateOrInsert(['id' => $account->id], $this->columns($account));

        DB::table('account_roles')->where('user_id', $account->id)->delete();
        DB::table('account_roles')->insert(array_map(
            fn (Role $role) => ['user_id' => $account->id, 'role' => $role->value],
            $account->roles,
        ));
    }

    public function get(string $id): ?Account
    {
        $row = DB::table('users')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function emailIsTaken(Email $email): bool
    {
        return DB::table('users')->whereRaw('lower(email) = ?', [$email->value])->exists();
    }

    public function list(): array
    {
        return $this->hydrateAll(DB::table('users')->orderBy('name')->orderBy('id')->get());
    }

    public function ofProducer(string $producerId): ?Account
    {
        $row = DB::table('users')->where('producer_id', $producerId)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function producerHasAccount(string $producerId): bool
    {
        return DB::table('users')->where('producer_id', $producerId)->exists();
    }

    public function producerAccounts(): array
    {
        return array_values(DB::table('users')->whereNotNull('producer_id')->orderBy('id')->get()
            ->map($this->hydrate(...))
            ->all());
    }

    public function hasManager(): bool
    {
        return DB::table('account_roles')->where('role', Role::Manager->value)->exists();
    }

    public function membersOf(string $householdId): array
    {
        return $this->hydrateAll(DB::table('users')->where('household_id', $householdId)->orderBy('name')->orderBy('id')->get());
    }

    public function hasOtherActiveManager(string $accountId): bool
    {
        return $this->managerRows()->whereNull('deactivated_at')->where('id', '<>', $accountId)->exists();
    }

    public function lockManagers(): void
    {
        // In id order, so two transactions locking the same managers cannot deadlock. The managers are read
        // afterwards, in a new statement: it sees what the transaction that held the locks committed.
        $this->managerRows()->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function managerRows(): Builder
    {
        return DB::table('users')->whereIn('id', DB::table('account_roles')->select('user_id')->where('role', Role::Manager->value));
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<Account>
     */
    private function hydrateAll(Collection $rows): array
    {
        return array_values($rows->map($this->hydrate(...))->all());
    }

    /** @return array<string, mixed> */
    private function columns(Account $account): array
    {
        $columns = [
            'name' => $account->name,
            'email' => $account->email->value,
            'household_id' => $account->householdId,
            'producer_id' => $account->producerId,
            'activated_at' => $account->activatedAt?->format(DATE_ATOM),
            'deactivated_at' => $account->deactivatedAt?->format(DATE_ATOM),
            'updated_at' => Carbon::now(),
        ];

        $stored = DB::table('users')->where('id', $account->id)->first(['activated_at']);

        if ($stored !== null) {
            // Accepting the invitation proves the address; later saves must not touch the verification time.
            if ($stored->activated_at === null && $account->activatedAt !== null) {
                // A plain `timestamp` column drops the offset, so write the UTC wall-clock time.
                $columns['email_verified_at'] = $account->activatedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }

            return $columns;
        }

        // The password column is required; nobody knows this one, so the account cannot log in until it is replaced.
        return $columns + ['password' => Hash::make(Str::random(64)), 'created_at' => Carbon::now()];
    }

    private function hydrate(stdClass $row): Account
    {
        $roles = array_values(DB::table('account_roles')->where('user_id', $row->id)->orderBy('role')->pluck('role')
            ->map(fn (string $role) => Role::from($role))
            ->all());

        return Account::restore(
            $row->id,
            $row->name,
            Email::from($row->email),
            $roles,
            $row->household_id,
            $row->producer_id,
            $row->activated_at === null ? null : new DateTimeImmutable($row->activated_at),
            $row->deactivated_at === null ? null : new DateTimeImmutable($row->deactivated_at),
        );
    }
}
