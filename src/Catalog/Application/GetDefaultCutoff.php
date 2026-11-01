<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** A producer's own default cutoff, for its cutoff page. */
final class GetDefaultCutoff
{
    public function __construct(private readonly ProducerRepository $producers) {}

    public function __invoke(Actor $actor, string $producerId): CutoffRuleData
    {
        Gate::forUser($actor)->authorize('owns', ['producer', $producerId]);

        $rule = ($this->producers->get($producerId) ?? throw ProducerNotFound::withId($producerId))->cutoffRule();

        return new CutoffRuleData($rule->daysBefore, $rule->time);
    }
}
