<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Product;

final readonly class ProductAdministrationData
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public bool $active,
        public bool $stock,
        public bool $used,
        public bool $canEdit = false,
        public bool $canChangeClassification = false,
        public bool $canDelete = false,
    ) {}

    public static function of(Product $product, bool $used, bool $canEdit = false, bool $canChangeClassification = false, bool $canDelete = false): self
    {
        return new self(
            $product->id, $product->producerId, $product->name(), $product->unit(),
            $product->price()->cents, $product->isVariable(), $product->containersPerUnit()?->toString(),
            $product->isActive(), $product->isStock(), $used, $canEdit, $canChangeClassification, $canDelete,
        );
    }
}
