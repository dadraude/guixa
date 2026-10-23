<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Producer;

final readonly class ProducerData
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public int $cutoffDaysBefore,
        public string $cutoffTime,
    ) {}

    public static function of(Producer $producer): self
    {
        $rule = $producer->cutoffRule();

        return new self($producer->id, $producer->name(), $producer->isActive(), $rule->daysBefore, $rule->time);
    }
}
