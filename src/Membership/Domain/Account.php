<?php

namespace Guixa\Membership\Domain;

use DateTimeImmutable;
use Guixa\Membership\Domain\Error\InvitationAlreadyAccepted;
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
        public readonly array $roles,
        public readonly ?string $householdId,
        public readonly ?string $producerId,
        public private(set) ?DateTimeImmutable $activatedAt,
        public readonly ?DateTimeImmutable $deactivatedAt,
    ) {}

    /** A person added to a household: a member who cannot log in until the invitation is accepted (R34). */
    public static function inviteMember(string $id, string $name, Email $email, string $householdId): self
    {
        return new self($id, trim($name), $email, [Role::Member], $householdId, null, null, null);
    }

    /** The one account of a producer: it cannot log in until the invitation is accepted (R34, R36). */
    public static function inviteProducer(string $id, string $name, Email $email, string $producerId): self
    {
        return new self($id, trim($name), $email, [Role::Producer], null, $producerId, null, null);
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

    /** Login needs an accepted invitation and an account that is not deactivated (R34, R36b). */
    public function canLogIn(): bool
    {
        return $this->hasAcceptedInvitation() && $this->deactivatedAt === null;
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
