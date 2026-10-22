<?php

namespace Guixa\Membership\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** The one live invitation of an account; issuing another replaces it, so the old link stops working (R34). */
final readonly class Invitation
{
    private const VALID_FOR = '+7 days';

    private function __construct(
        public string $accountId,
        public string $tokenHash,
        public DateTimeImmutable $sentAt,
    ) {}

    public static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function issue(string $accountId, string $token, DateTimeImmutable $now): self
    {
        return new self($accountId, self::hash($token), $now);
    }

    public static function restore(string $accountId, string $tokenHash, DateTimeImmutable $sentAt): self
    {
        return new self($accountId, $tokenHash, $sentAt);
    }

    /** Only the hash is stored, so a leaked table cannot be used to accept invitations. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function expiresAt(): DateTimeImmutable
    {
        // Elapsed hours, not calendar days: the result must not depend on the timezone sentAt carries.
        return $this->sentAt->setTimezone(new DateTimeZone('UTC'))
            ->modify(self::VALID_FOR)
            ->setTimezone($this->sentAt->getTimezone());
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt();
    }
}
