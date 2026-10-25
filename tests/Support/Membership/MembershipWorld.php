<?php

namespace Tests\Support\Membership;

use Guixa\Membership\Application\AcceptInvitation;
use Guixa\Membership\Application\AddMember;
use Guixa\Membership\Application\CanLogIn;
use Guixa\Membership\Application\ChangeInvitationEmail;
use Guixa\Membership\Application\CreateFirstManager;
use Guixa\Membership\Application\CreateHousehold;
use Guixa\Membership\Application\DeactivateAccount;
use Guixa\Membership\Application\DeactivateEmptyHousehold;
use Guixa\Membership\Application\DeactivateHousehold;
use Guixa\Membership\Application\GetHousehold;
use Guixa\Membership\Application\GetHouseholdDeactivationWarning;
use Guixa\Membership\Application\GetHouseholds;
use Guixa\Membership\Application\GrantManagerRole;
use Guixa\Membership\Application\InviteProducerAccount;
use Guixa\Membership\Application\ListAccounts;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Membership\Application\ListHouseholdsToSettle;
use Guixa\Membership\Application\ListProducerAccounts;
use Guixa\Membership\Application\MoveMember;
use Guixa\Membership\Application\ReactivateAccount;
use Guixa\Membership\Application\ReactivateHousehold;
use Guixa\Membership\Application\RemoveManagerRole;
use Guixa\Membership\Application\RenameHousehold;
use Guixa\Membership\Application\ResendInvitation;
use Guixa\Membership\Application\RestoreProducerRole;
use Guixa\Membership\Application\SendInvitation;
use Guixa\Membership\Application\SuspendProducerRole;
use Guixa\Membership\Application\UnlinkMember;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\FakeHouseholdBalances;
use Tests\Support\InMemory\FakeHouseholdPickups;
use Tests\Support\InMemory\FakeProducerDirectory;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryAccountRepository;
use Tests\Support\InMemory\InMemoryHouseholdRepository;
use Tests\Support\InMemory\InMemoryInvitationRepository;
use Tests\Support\InMemory\RecordingAccountPasswords;
use Tests\Support\InMemory\RecordingInvitationSender;

/** The Membership use cases wired to in-memory adapters, for the Application tests. */
final class MembershipWorld
{
    public readonly FrozenClock $clock;

    public readonly FakeHouseholdBalances $balances;

    public readonly FakeHouseholdPickups $pickups;

    public readonly DeactivateHousehold $deactivateHousehold;

    public readonly ReactivateHousehold $reactivateHousehold;

    public readonly GetHouseholdDeactivationWarning $getHouseholdDeactivationWarning;

    public readonly ListHouseholdsToSettle $listHouseholdsToSettle;

    public readonly InMemoryHouseholdRepository $households;

    public readonly InMemoryAccountRepository $accounts;

    public readonly InMemoryInvitationRepository $invitations;

    public readonly RecordingInvitationSender $sender;

    public readonly RecordingAccountPasswords $passwords;

    public readonly FakeProducerDirectory $producerDirectory;

    public readonly ImmediateTransactionRunner $transactions;

    public readonly CreateHousehold $createHousehold;

    public readonly CreateFirstManager $createFirstManager;

    public readonly RenameHousehold $renameHousehold;

    public readonly AddMember $addMember;

    public readonly ResendInvitation $resendInvitation;

    public readonly ChangeInvitationEmail $changeInvitationEmail;

    public readonly InviteProducerAccount $inviteProducerAccount;

    public readonly AcceptInvitation $acceptInvitation;

    public readonly CanLogIn $canLogIn;

    public readonly ListHouseholds $listHouseholds;

    public readonly GetHousehold $getHousehold;

    public readonly GetHouseholds $getHouseholds;

    public readonly MoveMember $moveMember;

    public readonly UnlinkMember $unlinkMember;

    public readonly GrantManagerRole $grantManagerRole;

    public readonly RemoveManagerRole $removeManagerRole;

    public readonly DeactivateAccount $deactivateAccount;

    public readonly ReactivateAccount $reactivateAccount;

    public readonly ListAccounts $listAccounts;

    public readonly ListProducerAccounts $listProducerAccounts;

    public readonly SuspendProducerRole $suspendProducerRole;

    public readonly RestoreProducerRole $restoreProducerRole;

    public readonly Actor $manager;

    public readonly Actor $member;

    public function __construct(string $now = '2026-10-22 10:00')
    {
        $this->clock = new FrozenClock($now);
        $this->balances = new FakeHouseholdBalances;
        $this->pickups = new FakeHouseholdPickups;
        $this->households = new InMemoryHouseholdRepository;
        $this->accounts = new InMemoryAccountRepository;
        $this->invitations = new InMemoryInvitationRepository;
        $this->sender = new RecordingInvitationSender;
        $this->passwords = new RecordingAccountPasswords;
        $this->producerDirectory = new FakeProducerDirectory;
        $this->transactions = new ImmediateTransactionRunner;

        $sendInvitation = new SendInvitation($this->invitations, $this->sender, $this->transactions, $this->clock);

        $this->createHousehold = new CreateHousehold($this->households);
        $this->createFirstManager = new CreateFirstManager($this->accounts, $sendInvitation, $this->transactions);
        $this->renameHousehold = new RenameHousehold($this->households, $this->transactions);
        $this->addMember = new AddMember($this->households, $this->accounts, $sendInvitation, $this->transactions);
        $this->inviteProducerAccount = new InviteProducerAccount(
            $this->producerDirectory,
            $this->accounts,
            $sendInvitation,
            $this->transactions,
        );
        $this->resendInvitation = new ResendInvitation($this->accounts, $sendInvitation, $this->transactions);
        $this->changeInvitationEmail = new ChangeInvitationEmail($this->accounts, $sendInvitation, $this->transactions);
        $this->acceptInvitation = new AcceptInvitation(
            $this->invitations,
            $this->accounts,
            $this->passwords,
            $this->transactions,
            $this->clock,
        );
        $this->canLogIn = new CanLogIn($this->accounts);
        $this->listHouseholds = new ListHouseholds($this->households, $this->accounts);
        $this->deactivateHousehold = new DeactivateHousehold($this->households, $this->accounts, $this->transactions, $this->clock);
        $this->reactivateHousehold = new ReactivateHousehold($this->households, $this->transactions);
        $this->getHouseholdDeactivationWarning = new GetHouseholdDeactivationWarning($this->households, $this->balances, $this->pickups);
        $this->listHouseholdsToSettle = new ListHouseholdsToSettle($this->households, $this->balances, $this->pickups);
        $this->listProducerAccounts = new ListProducerAccounts($this->accounts);
        $this->getHouseholds = new GetHouseholds($this->households);
        $this->getHousehold = new GetHousehold($this->households, $this->accounts, $this->invitations, $this->clock);

        $deactivateEmptyHousehold = new DeactivateEmptyHousehold($this->households, $this->accounts, $this->clock);

        $this->moveMember = new MoveMember($this->households, $this->accounts, $deactivateEmptyHousehold, $this->transactions);
        $this->unlinkMember = new UnlinkMember($this->accounts, $this->households, $deactivateEmptyHousehold, $this->transactions);
        $this->grantManagerRole = new GrantManagerRole($this->accounts, $this->households, $this->transactions);
        $this->removeManagerRole = new RemoveManagerRole($this->accounts, $this->households, $this->transactions);
        $this->deactivateAccount = new DeactivateAccount($this->accounts, $this->households, $deactivateEmptyHousehold, $this->transactions, $this->clock);
        $this->reactivateAccount = new ReactivateAccount($this->accounts, $this->households, $this->transactions);
        $this->listAccounts = new ListAccounts($this->accounts, $this->households);
        $this->suspendProducerRole = new SuspendProducerRole($this->accounts);
        $this->restoreProducerRole = new RestoreProducerRole($this->accounts);

        $this->manager = new Actor('00000000-0000-7000-8000-00000000000a', [Role::Manager]);
        $this->member = new Actor('00000000-0000-7000-8000-00000000000b', [Role::Member], '00000000-0000-7000-8000-00000000000c');
    }

    /** An active account that holds only the manager role, as the installation command would leave it. */
    public function addManager(string $name, string $email): string
    {
        $account = Account::restore(
            $this->accounts->nextId(),
            $name,
            Email::from($email),
            [Role::Manager],
            null,
            null,
            $this->clock->now(),
            null,
        );
        $this->accounts->save($account);

        return $account->id;
    }
}
