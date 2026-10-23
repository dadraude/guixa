<?php

namespace Guixa\Catalog\Application;

final readonly class CutoffRuleData
{
    public function __construct(
        public int $daysBefore,
        public string $time,
    ) {}
}
