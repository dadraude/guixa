<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;

/** Internal query for Ordering: a producer's default cutoff rule and whether it is active (R5). */
final class GetProducerCutoffRule
{
    public function __construct(private readonly ProducerRepository $producers) {}

    public function __invoke(string $producerId): ?ProducerCutoffRuleData
    {
        $producer = $this->producers->get($producerId);

        if ($producer === null) {
            return null;
        }

        $rule = $producer->cutoffRule();

        return new ProducerCutoffRuleData($producer->isActive(), $rule->daysBefore, $rule->time);
    }
}
