<?php

namespace Guixa\Stock\Application;

final readonly class ReturnItemData
{
    public function __construct(
        public string $id,
        public string $name,
        public int $amountCents,
        public bool $active,
    ) {}
}
