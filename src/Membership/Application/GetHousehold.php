<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class GetHousehold
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly AccountRepository $accounts,
        private readonly InvitationRepository $invitations,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $householdId): HouseholdDetail
    {
        Gate::forUser($actor)->authorize('manage');

        $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);

        return new HouseholdDetail(
            $household->id,
            $household->name,
            $household->isActive(),
            array_map($this->memberView(...), $this->accounts->membersOf($household->id)),
        );
    }

    private function memberView(Account $account): MemberView
    {
        $invitation = $account->hasAcceptedInvitation() ? null : $this->invitations->forAccount($account->id);

        return new MemberView(
            $account->id,
            $account->name,
            $account->email->value,
            ! $account->hasAcceptedInvitation(),
            $invitation?->expiresAt()->setTimezone($this->clock->timezone())->format(DATE_ATOM),
            $invitation?->isExpiredAt($this->clock->now()) ?? false,
        );
    }
}
