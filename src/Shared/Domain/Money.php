<?php

namespace Guixa\Shared\Domain;

final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function negate(): self
    {
        return new self(-$this->cents);
    }

    /**
     * Rounds half up to the cent. A negative amount (a credit) rounds by magnitude, so that
     * negating before or after multiplying gives the same result.
     */
    public function multiply(Quantity $quantity): self
    {
        $magnitude = abs($this->cents) * $quantity->thousandths;
        $roundedCents = intdiv($magnitude + 500, 1000);

        return new self($this->cents < 0 ? -$roundedCents : $roundedCents);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
