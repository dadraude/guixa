<?php

namespace Guixa\Catalog\Application;

final readonly class ProducerCutoffRuleData
{
    public function __construct(
        public bool $active,
        public int $daysBefore,
        public string $time,
    ) {}
}
