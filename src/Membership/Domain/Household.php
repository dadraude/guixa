<?php

namespace Guixa\Membership\Domain;

use DateTimeImmutable;
use Guixa\Membership\Domain\Error\InvalidHouseholdName;

final class Household
{
    public private(set) string $name;

    private function __construct(
        public readonly string $id,
        string $name,
        public private(set) ?DateTimeImmutable $deactivatedAt,
    ) {
        $this->name = self::validName($name);
    }

    /** Created active, even with no members (R35a). */
    public static function create(string $id, string $name): self
    {
        return new self($id, $name, null);
    }

    public static function restore(string $id, string $name, ?DateTimeImmutable $deactivatedAt): self
    {
        return new self($id, $name, $deactivatedAt);
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function rename(string $name): void
    {
        $this->name = self::validName($name);
    }

    public function reactivate(): void
    {
        $this->deactivatedAt = null;
    }

    private static function validName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidHouseholdName::blank();
        }

        return $trimmed;
    }
}
