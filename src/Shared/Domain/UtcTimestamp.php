<?php

namespace Guixa\Shared\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Persistence edge of ADR 0002 rule 5: stored text is always UTC, domain values keep their own offset. */
final class UtcTimestamp
{
    private const FORMAT = 'Y-m-d H:i:s.u';

    /** @return ($moment is null ? null : string) */
    public static function toStorage(?DateTimeInterface $moment): ?string
    {
        return $moment === null ? null : DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    /** @return ($text is null ? null : DateTimeImmutable) */
    public static function fromStorage(?string $text): ?DateTimeImmutable
    {
        return $text === null ? null : new DateTimeImmutable($text, new DateTimeZone('UTC'));
    }
}
