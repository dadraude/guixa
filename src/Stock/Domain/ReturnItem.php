<?php

namespace Guixa\Stock\Domain;

use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\Error\InvalidReturnAmount;
use Guixa\Stock\Domain\Error\NameRequired;

/** A returnable container credited to a household at pickup (R25, R20a). */
final class ReturnItem
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private Money $amount,
        private bool $active,
    ) {}

    public static function create(string $id, string $name, Money $amount): self
    {
        $item = new self($id, '', $amount, true);
        $item->change($name, $amount);

        return $item;
    }

    public static function restore(string $id, string $name, Money $amount, bool $active): self
    {
        return new self($id, $name, $amount, $active);
    }

    public function change(string $name, Money $amount): void
    {
        $name = trim($name);

        if ($name === '') {
            throw NameRequired::create();
        }

        if ($amount->cents <= 0) {
            throw InvalidReturnAmount::create();
        }

        $this->name = $name;
        $this->amount = $amount;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function reactivate(): void
    {
        $this->active = true;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
