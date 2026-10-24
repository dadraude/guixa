<?php

namespace Guixa\Catalog\Domain;

use Guixa\Catalog\Domain\Error\NameRequired;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;

/** Supplies products to the cooperative and has one default cutoff rule (R5). */
final class Producer
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private bool $active,
        private CutoffRule $cutoffRule,
    ) {}

    public static function create(string $id, string $name, CutoffRule $cutoffRule): self
    {
        $name = trim($name);

        if ($name === '') {
            throw NameRequired::create();
        }

        return new self($id, $name, true, $cutoffRule);
    }

    public static function restore(string $id, string $name, bool $active, CutoffRule $cutoffRule): self
    {
        return new self($id, $name, $active, $cutoffRule);
    }

    /** Only cycles where the producer starts participating afterwards use it (R5). */
    public function changeCutoffRule(CutoffRule $cutoffRule): void
    {
        $this->cutoffRule = $cutoffRule;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function reactivate(): void
    {
        $this->active = true;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function cutoffRule(): CutoffRule
    {
        return $this->cutoffRule;
    }
}
