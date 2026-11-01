<?php

namespace Guixa\Sales\Domain;

use Guixa\Sales\Domain\ValueObject\ReturnItemSnapshot;

interface ReturnItems
{
    public function get(string $id): ?ReturnItemSnapshot;
}
