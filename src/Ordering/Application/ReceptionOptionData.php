<?php

namespace Guixa\Ordering\Application;

/** A product of the producer that a manager can add to the reception (R14). */
final readonly class ReceptionOptionData
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $variable,
    ) {}
}
