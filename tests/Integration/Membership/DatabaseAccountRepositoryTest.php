<?php

namespace Tests\Integration\Membership;

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProducerRepository;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Membership\Infrastructure\Persistence\DatabaseAccountRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseHouseholdRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Contracts\AccountRepositoryContract;
use Tests\TestCase;

final class DatabaseAccountRepositoryTest extends TestCase
{
    use AccountRepositoryContract;
    use RefreshDatabase;

    private ?string $householdId = null;

    private ?string $producerId = null;

    protected function repository(): AccountRepository
    {
        return new DatabaseAccountRepository;
    }

    protected function producerId(): string
    {
        if ($this->producerId === null) {
            $producers = new EloquentProducerRepository;
            $producer = Producer::create($producers->nextId(), 'Baker', CutoffRule::of(3, '20:00'));
            $producers->add($producer);
            $this->producerId = $producer->id;
        }

        return $this->producerId;
    }

    protected function householdId(): string
    {
        if ($this->householdId === null) {
            $households = new DatabaseHouseholdRepository;
            $household = Household::create($households->nextId(), 'Casa Roca');
            $households->save($household);
            $this->householdId = $household->id;
        }

        return $this->householdId;
    }

    public function test_a_new_account_cannot_log_in_until_it_has_a_password_it_chose(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember(
            $repository->nextId(),
            'Anna',
            Email::from('anna@example.org'),
            $this->householdId(),
        );

        $repository->save($account);

        $this->assertDatabaseHas('users', ['id' => $account->id, 'activated_at' => null, 'email_verified_at' => null]);
        $this->assertDatabaseMissing('account_roles', ['user_id' => $account->id]);
    }

    public function test_saving_an_active_account_keeps_its_email_verification_time(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember(
            $repository->nextId(),
            'Anna',
            Email::from('anna@example.org'),
            $this->householdId(),
        );
        $repository->save($account);
        $account->acceptInvitation(new \DateTimeImmutable('2026-10-22 10:00:00+00:00'));
        $repository->save($account);

        DB::table('users')->where('id', $account->id)->update(['email_verified_at' => null]);
        $repository->save($repository->get($account->id));

        $this->assertDatabaseHas('users', ['id' => $account->id, 'email_verified_at' => null]);
    }

    public function test_the_email_verification_time_is_stored_in_utc(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember(
            $repository->nextId(),
            'Anna',
            Email::from('anna@example.org'),
            $this->householdId(),
        );
        $repository->save($account);
        $account->acceptInvitation(new \DateTimeImmutable('2026-10-22 12:00:00+02:00'));

        $repository->save($account);

        $this->assertDatabaseHas('users', ['id' => $account->id, 'email_verified_at' => '2026-10-22 10:00:00']);
    }
}
