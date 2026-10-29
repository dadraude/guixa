<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProductUsage;

final class IsProductUsed
{
    public function __construct(private readonly ProductUsage $usage) {}

    public function __invoke(string $id): bool
    {
        return $this->usage->hasAny($id);
    }
}
