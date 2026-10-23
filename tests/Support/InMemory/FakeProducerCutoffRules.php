<?php

namespace Tests\Support\InMemory;

use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;

final class FakeProducerCutoffRules implements ProducerCutoffRules
{
    /** @var array<string, CutoffRule> */
    private array $active = [];

    public function activate(string $producerId, int $daysBefore, string $time): void
    {
        $this->active[$producerId] = CutoffRule::of($daysBefore, $time);
    }

    public function deactivate(string $producerId): void
    {
        unset($this->active[$producerId]);
    }

    public function activeRuleOf(string $producerId): ?CutoffRule
    {
        return $this->active[$producerId] ?? null;
    }
}
