<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountListing;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\AccountStatus;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;
use PHPUnit\Framework\Attributes\Test;

/** Integration tests also need household and producer rows to point at: they implement `householdId()` and `producerId()`. */
trait AccountRepositoryContract
{
    abstract protected function repository(): AccountRepository;

    abstract protected function householdId(): string;

    /** Issues the account's invitation as sent at that instant, as the real invitation repository would. */
    abstract protected function inviteAt(Account $account, DateTimeImmutable $sentAt): void;

    /** An existing producer for producer accounts to point at. */
    abstract protected function producerId(): string;

    #[Test]
    public function it_finds_what_was_saved(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());

        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame('Anna', $found?->name);
        $this->assertSame('anna@example.org', $found->email->value);
        $this->assertSame($this->householdId(), $found->householdId);
        $this->assertSame([], $found->roles);
        $this->assertFalse($found->hasAcceptedInvitation());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_answers_the_names_of_the_accounts_asked_for_and_leaves_out_unknown_ones(): void
    {
        $repository = $this->repository();
        $anna = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $biel = Account::inviteMember($repository->nextId(), 'Biel', Email::from('biel@example.org'), $this->householdId());
        $repository->save($anna);
        $repository->save($biel);
        $biel->acceptInvitation(new DateTimeImmutable('2026-10-22 10:00:00+00:00'));
        $biel->deactivate(new DateTimeImmutable('2026-10-23 10:00:00+00:00'));
        $repository->save($biel);

        $names = $repository->namesByIds([$anna->id, $biel->id, $repository->nextId()]);

        $this->assertSame([$anna->id => 'Anna', $biel->id => 'Biel'], $names);
        $this->assertSame([], $repository->namesByIds([]));
    }

    #[Test]
    public function it_keeps_the_changes_of_a_saved_account(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $repository->save($account);

        $account->changeInvitationEmail(Email::from('anna.new@example.org'));
        $account->acceptInvitation(new DateTimeImmutable('2026-10-22 10:00:00+00:00'));
        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame('anna.new@example.org', $found?->email->value);
        $this->assertTrue($found->hasAcceptedInvitation());
    }

    #[Test]
    public function it_tells_whether_an_email_is_taken(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));

        $this->assertTrue($repository->emailIsTaken(Email::from('ANNA@example.org')));
        $this->assertFalse($repository->emailIsTaken(Email::from('biel@example.org')));
    }

    #[Test]
    public function it_lists_the_members_of_a_household(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));
        $repository->save(Account::inviteMember($repository->nextId(), 'Biel', Email::from('biel@example.org'), $this->householdId()));

        $this->assertCount(2, $repository->membersOf($this->householdId()));
        $this->assertSame([], $repository->membersOf($repository->nextId()));
    }

    #[Test]
    public function it_keeps_the_roles_household_and_deactivation_of_a_saved_account(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $repository->save($account);

        $account->grantRole(Role::Manager);
        $account->deactivate(new DateTimeImmutable('2026-10-22 10:00:00+00:00'));
        $repository->save($account);
        $repository->saveHouseholdLink($account);

        $found = $repository->get($account->id);
        $this->assertSame([Role::Manager], $found->roles);
        $this->assertNull($found->householdId);
        $this->assertEquals(new DateTimeImmutable('2026-10-22 10:00:00+00:00'), $found->deactivatedAt);

        $found->removeRole(Role::Manager);
        $found->reactivate();
        $repository->save($found);

        $reloaded = $repository->get($account->id);
        $this->assertSame([], $reloaded->roles);
        $this->assertNull($reloaded->deactivatedAt);
    }

    #[Test]
    public function ordinary_account_saves_preserve_the_stored_household_link(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $repository->save($account);
        $stale = $repository->get($account->id);
        $linked = $repository->get($account->id);
        $linked->unlink();
        $repository->saveHouseholdLink($linked);

        $stale->grantRole(Role::Manager);
        $repository->save($stale);

        $stored = $repository->get($account->id);
        $this->assertNull($stored->householdId);
        $this->assertSame([Role::Manager], $stored->roles);
    }

    #[Test]
    public function it_unlinks_an_account_from_its_household(): void
    {
        $repository = $this->repository();
        $account = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $repository->save($account);

        $account->unlink();
        $repository->saveHouseholdLink($account);

        $this->assertNull($repository->get($account->id)->householdId);
        $this->assertSame([], $repository->membersOf($this->householdId()));
    }

    #[Test]
    public function it_lists_every_account_by_name(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Biel', Email::from('biel@example.org'), $this->householdId()));
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));

        $this->assertSame(['Anna', 'Biel'], array_map(fn (Account $account) => $account->name, $repository->list()));
    }

    #[Test]
    public function it_tells_whether_an_active_manager_other_than_the_account_exists(): void
    {
        $repository = $this->repository();
        $accepted = new DateTimeImmutable('2026-10-01 10:00:00+00:00');
        $active = Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId());
        $active->grantRole(Role::Manager);
        $active->acceptInvitation($accepted);
        $deactivated = Account::inviteMember($repository->nextId(), 'Biel', Email::from('biel@example.org'), $this->householdId());
        $deactivated->grantRole(Role::Manager);
        $deactivated->acceptInvitation($accepted);
        $deactivated->deactivate(new DateTimeImmutable('2026-10-22 10:00:00+00:00'));
        $pending = Account::inviteMember($repository->nextId(), 'Eva', Email::from('eva@example.org'), $this->householdId());
        $pending->grantRole(Role::Manager);
        $member = Account::inviteMember($repository->nextId(), 'Carla', Email::from('carla@example.org'), $this->householdId());
        foreach ([$active, $deactivated, $pending, $member] as $account) {
            $repository->save($account);
        }

        $repository->lockManagers();

        $this->assertFalse($repository->hasOtherActiveManager($active->id));
        $this->assertTrue($repository->hasOtherActiveManager($deactivated->id));
        $this->assertTrue($repository->hasOtherActiveManager($member->id));
        $this->assertTrue($repository->hasOtherActiveManager($pending->id));

        $second = Account::inviteMember($repository->nextId(), 'Dan', Email::from('dan@example.org'), $this->householdId());
        $second->grantRole(Role::Manager);
        $second->acceptInvitation($accepted);
        $repository->save($second);

        $this->assertTrue($repository->hasOtherActiveManager($active->id));
    }

    #[Test]
    public function it_finds_a_producer_account_with_its_role_and_producer(): void
    {
        $repository = $this->repository();
        $account = Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId());

        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame([Role::Producer], $found?->roles);
        $this->assertSame($this->producerId(), $found->producerId);
        $this->assertNull($found->householdId);
    }

    #[Test]
    public function it_finds_the_account_of_a_producer(): void
    {
        $repository = $this->repository();
        $this->assertNull($repository->ofProducer($this->producerId()));

        $account = Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId());
        $repository->save($account);

        $this->assertSame($account->id, $repository->ofProducer($this->producerId())?->id);
    }

    #[Test]
    public function it_stores_a_suspended_producer_role_as_no_role(): void
    {
        $repository = $this->repository();
        $account = Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId());
        $account->suspendProducerRole();
        $repository->save($account);

        $found = $repository->get($account->id);
        $this->assertSame([], $found?->roles);
        $this->assertSame($this->producerId(), $found->producerId);
    }

    #[Test]
    public function it_tells_whether_a_producer_has_an_account(): void
    {
        $repository = $this->repository();
        $this->assertFalse($repository->producerHasAccount($this->producerId()));

        $repository->save(Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId()));

        $this->assertTrue($repository->producerHasAccount($this->producerId()));
    }

    #[Test]
    public function it_lists_only_the_accounts_that_belong_to_a_producer(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));
        $baker = Account::inviteProducer($repository->nextId(), 'Baker', Email::from('baker@example.org'), $this->producerId());
        $repository->save($baker);

        $ids = array_map(fn (Account $account) => $account->id, $repository->producerAccounts());

        $this->assertSame([$baker->id], $ids);
    }

    #[Test]
    public function it_tells_whether_a_manager_exists(): void
    {
        $repository = $this->repository();
        $repository->save(Account::inviteMember($repository->nextId(), 'Anna', Email::from('anna@example.org'), $this->householdId()));
        $this->assertFalse($repository->hasManager());

        $repository->save(Account::inviteManager($repository->nextId(), 'Marta', Email::from('marta@example.org')));
        $this->assertTrue($repository->hasManager());
    }

    private function staffAccount(string $name, string $email, array $roles = [], bool $accepted = true, bool $deactivated = false): Account
    {
        $repository = $this->repository();
        $account = Account::restore(
            $repository->nextId(), $name, Email::from($email), $roles, null, null,
            $accepted ? new DateTimeImmutable('2026-10-01 10:00:00+00:00') : null,
            $deactivated ? new DateTimeImmutable('2026-10-02 10:00:00+00:00') : null,
        );
        $repository->save($account);

        return $account;
    }

    /** @param list<AccountListing> $listings */
    private function names(array $listings): array
    {
        return array_map(fn ($listing) => $listing->name, $listings);
    }

    #[Test]
    public function it_lists_the_holders_of_each_staff_role_by_name_then_email_deactivated_included(): void
    {
        $repository = $this->repository();
        $this->staffAccount('bernat', 'b@example.org', [Role::Catalogue]);
        $this->staffAccount('Anna', 'z@example.org', [Role::Catalogue, Role::Manager]);
        $this->staffAccount('Anna', 'a@example.org', [Role::Catalogue]);
        $this->staffAccount('Carla', 'c@example.org', [Role::Receiver], deactivated: true);
        $this->staffAccount('Dora', 'd@example.org', [Role::Producer]);

        $holders = $repository->listStaffRoleHolders();

        $this->assertSame(['Anna', 'Anna', 'bernat'], $this->names($holders['catalogue']));
        $this->assertSame(['a@example.org', 'z@example.org', 'b@example.org'], array_map(fn ($listing) => $listing->email, $holders['catalogue']));
        $this->assertSame(['Anna'], $this->names($holders['manager']));
        $this->assertSame(['Carla'], $this->names($holders['receiver']));
        $this->assertSame([], $holders['finance']);
        $this->assertArrayNotHasKey('producer', $holders);
    }

    #[Test]
    public function it_tells_the_status_of_each_listed_account(): void
    {
        $repository = $this->repository();
        $now = new DateTimeImmutable('2026-11-09 10:00:00+00:00');
        $this->staffAccount('Active', 'a@example.org', [Role::Finance]);
        $this->staffAccount('Deactivated', 'd@example.org', [Role::Finance], deactivated: true);
        $pending = $this->staffAccount('Pending', 'p@example.org', [Role::Finance], accepted: false);
        $this->inviteAt($pending, new DateTimeImmutable('2026-11-08 10:00:00+00:00'));
        $expired = $this->staffAccount('Expired', 'e@example.org', [Role::Finance], accepted: false);
        $this->inviteAt($expired, new DateTimeImmutable('2026-10-20 10:00:00+00:00'));

        $statuses = [];
        foreach ($repository->listStaffRoleHolders()['finance'] as $listing) {
            $statuses[$listing->name] = $listing->status($now);
        }

        $this->assertSame([
            'Active' => AccountStatus::Active,
            'Deactivated' => AccountStatus::Deactivated,
            'Expired' => AccountStatus::InvitationExpired,
            'Pending' => AccountStatus::InvitationPending,
        ], $statuses);
    }

    #[Test]
    public function it_searches_candidates_by_name_or_email_without_holders_or_deactivated_accounts(): void
    {
        $repository = $this->repository();
        $this->staffAccount('Anna Roca', 'roca@example.org');
        $this->staffAccount('Bernat', 'anna.b@example.org');
        $this->staffAccount('Anna Holder', 'holder@example.org', [Role::Catalogue]);
        $this->staffAccount('Anna Gone', 'gone@example.org', deactivated: true);
        $this->staffAccount('Carla', 'carla@example.org');

        $found = $repository->searchStaffRoleCandidates(Role::Catalogue, 'ANNA', 20);

        $this->assertSame(['Anna Roca', 'Bernat'], $this->names($found));
        $this->assertSame(['Anna Holder'], $this->names($repository->searchStaffRoleCandidates(Role::Finance, 'holder', 20)));
    }

    #[Test]
    public function it_offers_pending_and_expired_invitations_as_candidates(): void
    {
        $repository = $this->repository();
        $pending = $this->staffAccount('Anna Pending', 'p@example.org', accepted: false);
        $this->inviteAt($pending, new DateTimeImmutable('2026-11-08 10:00:00+00:00'));
        $expired = $this->staffAccount('Anna Expired', 'e@example.org', accepted: false);
        $this->inviteAt($expired, new DateTimeImmutable('2026-10-20 10:00:00+00:00'));

        $found = $repository->searchStaffRoleCandidates(Role::Finance, 'anna', 20);

        $this->assertSame(['Anna Expired', 'Anna Pending'], $this->names($found));
    }

    #[Test]
    public function it_limits_orders_and_escapes_the_candidate_search(): void
    {
        $repository = $this->repository();
        foreach (['Anna C', 'Anna A', 'Anna B'] as $index => $name) {
            $this->staffAccount($name, "anna{$index}@example.org");
        }
        $this->staffAccount('Pau 100%', 'percent@example.org');

        $this->assertSame(['Anna A', 'Anna B'], $this->names($repository->searchStaffRoleCandidates(Role::Receiver, 'anna ', 2)));
        $this->assertSame(['Pau 100%'], $this->names($repository->searchStaffRoleCandidates(Role::Receiver, '%', 20)));
        $this->assertSame([], $repository->searchStaffRoleCandidates(Role::Receiver, '', 20));
        $this->assertSame([], $repository->searchStaffRoleCandidates(Role::Receiver, '   ', 20));
    }
}
