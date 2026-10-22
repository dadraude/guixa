<?php

namespace Guixa\Stock\Domain;

use Guixa\Shared\Domain\Error\InvalidQuantity;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\Error\InvalidContainersPerUnit;
use Guixa\Stock\Domain\Error\InvalidPrice;
use Guixa\Stock\Domain\Error\NameRequired;
use Guixa\Stock\Domain\Error\UnitRequired;

/** A product kept at the cooperative's premises and sold at pickup (R25). */
final class StockProduct
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private string $unit,
        private Money $price,
        private bool $variable,
        private ?Quantity $containersPerUnit,
        private bool $active,
    ) {}

    /** @param  ?string  $containersPerUnit  A quantity with at most 3 decimals, greater than zero. */
    public static function create(
        string $id,
        string $name,
        string $unit,
        Money $price,
        bool $variable,
        ?string $containersPerUnit,
    ): self {
        $product = new self($id, '', '', $price, $variable, null, true);
        $product->change($name, $unit, $price, $variable, $containersPerUnit);

        return $product;
    }

    public static function restore(
        string $id,
        string $name,
        string $unit,
        Money $price,
        bool $variable,
        ?Quantity $containersPerUnit,
        bool $active,
    ): self {
        return new self($id, $name, $unit, $price, $variable, $containersPerUnit, $active);
    }

    public function change(string $name, string $unit, Money $price, bool $variable, ?string $containersPerUnit): void
    {
        $name = trim($name);
        $unit = trim($unit);

        if ($name === '') {
            throw NameRequired::create();
        }

        if ($unit === '') {
            throw UnitRequired::create();
        }

        if ($price->isNegative()) {
            throw InvalidPrice::create();
        }

        $containers = $this->parseContainers($containersPerUnit);

        $this->name = $name;
        $this->unit = $unit;
        $this->price = $price;
        $this->variable = $variable;
        $this->containersPerUnit = $containers;
    }

    public function deactivate(): void
    {
        $this->active = false;
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

    private function parseContainers(?string $containersPerUnit): ?Quantity
    {
        if ($containersPerUnit === null) {
            return null;
        }

        try {
            $containers = Quantity::fromString($containersPerUnit);
        } catch (InvalidQuantity) {
            throw InvalidContainersPerUnit::create();
        }

        if ($containers->isZero()) {
            throw InvalidContainersPerUnit::create();
        }

        return $containers;
    }
}
