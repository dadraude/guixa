<?php

namespace Guixa\Ordering\Infrastructure\Catalog;

use Guixa\Catalog\Application\GetProducerCutoffRule;
use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;

/** Answers Ordering's questions about producers through Catalog's use cases (A4). */
final class CatalogProducerCutoffRules implements ProducerCutoffRules
{
    public function __construct(private readonly GetProducerCutoffRule $getProducerCutoffRule) {}

    public function activeRuleOf(string $producerId): ?CutoffRule
    {
        $rule = ($this->getProducerCutoffRule)($producerId);

        if ($rule === null || ! $rule->active) {
            return null;
        }

        return CutoffRule::of($rule->daysBefore, $rule->time);
    }
}
