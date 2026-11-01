<?php

namespace Guixa\Sales\Domain\ValueObject;

final readonly class ReturnItemSnapshot
{
    public function __construct(public string $id, public string $name, public int $amountCents, public bool $active) {}
}
