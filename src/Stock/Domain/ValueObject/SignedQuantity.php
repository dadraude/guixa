<?php

namespace Guixa\Stock\Domain\ValueObject;

use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Quantity;

/** A quantity that may be negative, in thousandths: stock movements add to or take from the available stock. */
final readonly class SignedQuantity
{
    private const int SCALE = 1000;

    private function __construct(public int $thousandths) {}

    public static function ofThousandths(int $thousandths): self
    {
        return new self($thousandths);
    }

    public static function fromQuantity(Quantity $quantity): self
    {
        return new self($quantity->thousandths);
    }

    public static function fromString(string $value): self
    {
        if (! str_starts_with($value, '-')) {
            return self::fromQuantity(Quantity::fromString($value));
        }

        $magnitude = substr($value, 1);

        if (str_starts_with($magnitude, '-')) {
            throw InvalidQuantity::malformed($value);
        }

        return new self(-Quantity::fromString($magnitude)->thousandths);
    }

    public function plus(self $other): self
    {
        return new self($this->thousandths + $other->thousandths);
    }

    public function negate(): self
    {
        return new self(-$this->thousandths);
    }

    public function isZero(): bool
    {
        return $this->thousandths === 0;
    }

    public function isPositive(): bool
    {
        return $this->thousandths > 0;
    }

    public function isNegative(): bool
    {
        return $this->thousandths < 0;
    }

    public function toString(): string
    {
        $sign = $this->thousandths < 0 ? '-' : '';
        $magnitude = abs($this->thousandths);

        return sprintf('%s%d.%03d', $sign, intdiv($magnitude, self::SCALE), $magnitude % self::SCALE);
    }
}
