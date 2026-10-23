<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\CutoffRule;

/** What Ordering asks Catalog about a producer when it starts participating (R5). */
interface ProducerCutoffRules
{
    /** The producer's default cutoff rule, or null when the producer is unknown or deactivated. */
    public function activeRuleOf(string $producerId): ?CutoffRule;
}
