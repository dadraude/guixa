<?php

namespace Guixa\Stock\Domain;

use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** Immutable Catalog metadata snapshot translated at Stock's read/lock port. */
final readonly class StockProduct
{
    private function __construct(
        public string $id,
        private string $name,
        private string $unit,
        private Money $price,
        private bool $variable,
        private ?Quantity $containersPerUnit,
        private bool $active,
        public string $producerId,
    ) {}

    public static function restore(
        string $id,
        string $name,
        string $unit,
        Money $price,
        bool $variable,
        ?Quantity $containersPerUnit,
        bool $active,
        string $producerId,
    ): self {
        return new self($id, $name, $unit, $price, $variable, $containersPerUnit, $active, $producerId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function isVariable(): bool
    {
        return $this->variable;
    }

    public function containersPerUnit(): ?Quantity
    {
        return $this->containersPerUnit;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
