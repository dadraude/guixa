<?php

namespace Guixa\Membership\Domain;

use DateTimeImmutable;
use Guixa\Membership\Domain\Error\AccountDeactivated;
use Guixa\Membership\Domain\Error\InvitationAlreadyAccepted;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Membership\Domain\Error\RoleAlreadyHeld;
use Guixa\Membership\Domain\Error\RoleNotHeld;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;

final class Account
{
    /**
     * @param  list<Role>  $roles
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public private(set) Email $email,
        public private(set) array $roles,
        public private(set) ?string $householdId,
        public readonly ?string $producerId,
        public private(set) ?DateTimeImmutable $activatedAt,
        public private(set) ?DateTimeImmutable $deactivatedAt,
    ) {}

    /** A person added to a household, so a member of it; they cannot log in until the invitation is accepted (R34). */
    public static function inviteMember(string $id, string $name, Email $email, string $householdId): self
    {
        return new self($id, trim($name), $email, [], $householdId, null, null, null);
    }

    /** The one account of a producer: it cannot log in until the invitation is accepted (R34, R36). */
    public static function inviteProducer(string $id, string $name, Email $email, string $producerId): self
    {
        return new self($id, trim($name), $email, [Role::Producer], null, $producerId, null, null);
    }

    /** The installation's first manager: no household, cannot log in until the invitation is accepted (R36a, D17). */
    public static function inviteManager(string $id, string $name, Email $email): self
    {
        return new self($id, trim($name), $email, [Role::Manager], null, null, null, null);
    }

    /**
     * @param  list<Role>  $roles
     */
    public static function restore(
        string $id,
        string $name,
        Email $email,
        array $roles,
        ?string $householdId,
        ?string $producerId,
        ?DateTimeImmutable $activatedAt,
        ?DateTimeImmutable $deactivatedAt,
    ): self {
        return new self($id, $name, $email, $roles, $householdId, $producerId, $activatedAt, $deactivatedAt);
    }

    public function hasAcceptedInvitation(): bool
    {
        return $this->activatedAt !== null;
    }

    /** Login needs an accepted invitation, an account that is not deactivated and something to do (R34, R36b, R35). */
    public function canLogIn(): bool
    {
        return $this->hasAcceptedInvitation() && $this->isActive() && $this->hasEffectiveRole();
    }

    /** A household link makes a member; otherwise the account needs a stored role (manager, producer). */
    private function hasEffectiveRole(): bool
    {
        return $this->householdId !== null || $this->roles !== [];
    }

    public function isManager(): bool
    {
        return in_array(Role::Manager, $this->roles, true);
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    /** Only a manager who accepted the invitation counts: a pending one cannot act yet (R1). */
    public function isActiveManager(): bool
    {
        return $this->isManager() && $this->hasAcceptedInvitation() && $this->isActive();
    }

    /** Moving relinks the same account; linking an unlinked one works the same way (R35). */
    public function moveTo(string $householdId): void
    {
        if (! $this->isActive()) {
            throw AccountDeactivated::for($this->id);
        }

        $this->householdId = $householdId;
    }

    public function unlink(): void
    {
        $this->householdId = null;
    }

    /** Pending and expired invitations can receive any staff role; a deactivated account cannot (R1, R2). */
    public function grantRole(Role $role): void
    {
        if (! $role->isStaff()) {
            throw NotAStaffRole::named($role->value);
        }

        if (! $this->isActive()) {
            throw AccountDeactivated::for($this->id);
        }

        if (in_array($role, $this->roles, true)) {
            throw RoleAlreadyHeld::for($this->id, $role);
        }

        $this->roles = [...$this->roles, $role];
    }

    /** A deactivated account keeps its roles and can lose them (R2). */
    public function removeRole(Role $role): void
    {
        if (! $role->isStaff()) {
            throw NotAStaffRole::named($role->value);
        }

        if (! in_array($role, $this->roles, true)) {
            throw RoleNotHeld::for($this->id, $role);
        }

        $this->roles = array_values(array_filter($this->roles, fn (Role $held) => $held !== $role));
    }

    /** The producer is deactivated: the account keeps its other roles (R3a). */
    public function suspendProducerRole(): void
    {
        $this->roles = array_values(array_filter($this->roles, fn (Role $role) => $role !== Role::Producer));
    }

    public function restoreProducerRole(): void
    {
        if (in_array(Role::Producer, $this->roles, true)) {
            return;
        }

        $this->roles = [...$this->roles, Role::Producer];
    }

    /** Deactivating unlinks the account from its household and keeps the rest of its history (R36b). */
    public function deactivate(DateTimeImmutable $now): void
    {
        if (! $this->isActive()) {
            return;
        }

        $this->deactivatedAt = $now;
        $this->householdId = null;
    }

    public function reactivate(): void
    {
        $this->deactivatedAt = null;
    }

    public function changeInvitationEmail(Email $email): void
    {
        $this->assertInvitationPending();

        $this->email = $email;
    }

    public function acceptInvitation(DateTimeImmutable $now): void
    {
        $this->assertInvitationPending();

        $this->activatedAt = $now;
    }

    public function assertInvitationPending(): void
    {
        if ($this->hasAcceptedInvitation()) {
            throw InvitationAlreadyAccepted::for($this->id);
        }
    }
}
