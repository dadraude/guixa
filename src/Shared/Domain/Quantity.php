<?php

namespace Guixa\Shared\Domain;

use Guixa\Shared\Domain\Error\InvalidQuantity;

final readonly class Quantity
{
    private const int SCALE = 1000;

    private function __construct(public int $thousandths) {}

    public static function ofThousandths(int $thousandths): self
    {
        if ($thousandths < 0) {
            throw InvalidQuantity::negative();
        }

        return new self($thousandths);
    }

    public static function fromString(string $value): self
    {
        if (str_starts_with($value, '-')) {
            throw InvalidQuantity::negative();
        }

        if (! preg_match('/^(\d{1,7})(?:\.(\d{1,3}))?\z/', $value, $parts)) {
            throw InvalidQuantity::malformed($value);
        }

        $fraction = str_pad($parts[2] ?? '', 3, '0');

        return new self(((int) $parts[1]) * self::SCALE + (int) $fraction);
    }

    public function toString(): string
    {
        return sprintf('%d.%03d', intdiv($this->thousandths, self::SCALE), $this->thousandths % self::SCALE);
    }

    public function isZero(): bool
    {
        return $this->thousandths === 0;
    }

    public function isWhole(): bool
    {
        return $this->thousandths % self::SCALE === 0;
    }

    public function ceilToWhole(): self
    {
        $wholeUnits = intdiv($this->thousandths + self::SCALE - 1, self::SCALE);

        return new self($wholeUnits * self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->thousandths === $other->thousandths;
    }
}
