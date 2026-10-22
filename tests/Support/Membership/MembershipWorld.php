<?php

namespace Tests\Support\Membership;

use Guixa\Membership\Application\AcceptInvitation;
use Guixa\Membership\Application\AddMember;
use Guixa\Membership\Application\CanLogIn;
use Guixa\Membership\Application\ChangeInvitationEmail;
use Guixa\Membership\Application\CreateFirstManager;
use Guixa\Membership\Application\CreateHousehold;
use Guixa\Membership\Application\GetHousehold;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Membership\Application\RenameHousehold;
use Guixa\Membership\Application\ResendInvitation;
use Guixa\Membership\Application\SendInvitation;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
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

    public readonly InMemoryHouseholdRepository $households;

    public readonly InMemoryAccountRepository $accounts;

    public readonly InMemoryInvitationRepository $invitations;

    public readonly RecordingInvitationSender $sender;

    public readonly RecordingAccountPasswords $passwords;

    public readonly ImmediateTransactionRunner $transactions;

    public readonly CreateHousehold $createHousehold;

    public readonly CreateFirstManager $createFirstManager;

    public readonly RenameHousehold $renameHousehold;

    public readonly AddMember $addMember;

    public readonly ResendInvitation $resendInvitation;

    public readonly ChangeInvitationEmail $changeInvitationEmail;

    public readonly AcceptInvitation $acceptInvitation;

    public readonly CanLogIn $canLogIn;

    public readonly ListHouseholds $listHouseholds;

    public readonly GetHousehold $getHousehold;

    public readonly Actor $manager;

    public readonly Actor $member;

    public function __construct(string $now = '2026-10-22 10:00')
    {
        $this->clock = new FrozenClock($now);
        $this->households = new InMemoryHouseholdRepository;
        $this->accounts = new InMemoryAccountRepository;
        $this->invitations = new InMemoryInvitationRepository;
        $this->sender = new RecordingInvitationSender;
        $this->passwords = new RecordingAccountPasswords;
        $this->transactions = new ImmediateTransactionRunner;

        $sendInvitation = new SendInvitation($this->invitations, $this->sender, $this->transactions, $this->clock);

        $this->createHousehold = new CreateHousehold($this->households);
        $this->createFirstManager = new CreateFirstManager($this->accounts, $sendInvitation, $this->transactions);
        $this->renameHousehold = new RenameHousehold($this->households);
        $this->addMember = new AddMember($this->households, $this->accounts, $sendInvitation, $this->transactions);
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
        $this->getHousehold = new GetHousehold($this->households, $this->accounts, $this->invitations, $this->clock);

        $this->manager = new Actor('00000000-0000-7000-8000-00000000000a', [Role::Manager]);
        $this->member = new Actor('00000000-0000-7000-8000-00000000000b', [Role::Member], '00000000-0000-7000-8000-00000000000c');
    }
}
