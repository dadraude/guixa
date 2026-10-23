<?php

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ValueObject\CutoffRule as CatalogCutoffRule;
use Guixa\Ordering\Infrastructure\Catalog\CatalogProducerCutoffRules;
use Tests\Support\Catalog\CatalogWorld;

beforeEach(function () {
    $this->world = new CatalogWorld;
    $this->rules = new CatalogProducerCutoffRules($this->world->getProducerCutoffRule);
});

it('hands Ordering the default cutoff rule of an active producer (R5)', function () {
    $id = ($this->world->createProducer)($this->world->manager, 'Baker', 3, '20:00');

    $rule = $this->rules->activeRuleOf($id);

    expect($rule->daysBefore)->toBe(3)
        ->and($rule->time)->toBe('20:00');
});

it('gives no rule for an unknown producer (S50)', function () {
    expect($this->rules->activeRuleOf($this->world->producers->nextId()))->toBeNull();
});

it('gives no rule for a deactivated producer (S50)', function () {
    $id = $this->world->producers->nextId();
    $this->world->producers->add(Producer::restore($id, 'Baker', false, CatalogCutoffRule::of(3, '20:00')));

    expect($this->rules->activeRuleOf($id))->toBeNull();
});
