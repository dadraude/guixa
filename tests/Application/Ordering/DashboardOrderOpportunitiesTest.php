<?php

use Guixa\Ordering\Application\DashboardOpportunityScope;
use Guixa\Ordering\Application\ReadDashboardOrderOpportunities;
use Guixa\Shared\Application\Actor;
use Tests\Support\InMemory\FakeReceptionProducerNames;
use Tests\Support\Ordering\OrderingWorld;

const OPPORTUNITY_HOUSEHOLD = '00000000-0000-7000-8000-0000000000a1';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->world->households->know(OPPORTUNITY_HOUSEHOLD, 'Household A');
    $this->member = Actor::memberOf('00000000-0000-7000-8000-0000000000aa', OPPORTUNITY_HOUSEHOLD);
    foreach ([OrderingWorld::BAKER, OrderingWorld::GROWER] as $producerId) {
        $this->world->rules->activate($producerId, 3, '20:00');
    }
    $this->products = [
        OrderingWorld::BAKER => $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, variable: false),
        OrderingWorld::GROWER => $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 280, variable: false),
    ];
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->names = new FakeReceptionProducerNames;
    $this->names->know(OrderingWorld::BAKER, 'Baker');
    $this->names->know(OrderingWorld::GROWER, 'Grower');
    $this->opportunities = fn () => new ReadDashboardOrderOpportunities($this->world->cycles, $this->names, $this->world->clock);
});

it('gives producer P only its own sent summary, with no household or other producer data (S2, R11)', function () {
    foreach ([OrderingWorld::BAKER, OrderingWorld::GROWER] as $producerId) {
        ($this->world->setOrderLine)($this->member, $this->cycleId, $this->products[$producerId], '2');
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, $producerId);
    }

    $summaries = ($this->opportunities)()(DashboardOpportunityScope::SentSummaries, OrderingWorld::BAKER);

    expect($summaries)->toHaveCount(1)
        ->and(get_object_vars($summaries[0]))->toBe(['cycleId' => $this->cycleId, 'pickupDate' => '2026-11-07']);
});

it('lists the open window with each open producer\'s cutoff, earliest first, and nothing about households (S3, R11, R12)', function () {
    // Cutoff rule 3 days before at 20:00: Wednesday 4 November 20:00 for Saturday 7 November.
    ($this->world->setOrderLine)($this->member, $this->cycleId, $this->products[OrderingWorld::BAKER], '2');
    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::GROWER, '2026-11-03T18:00');

    $windows = ($this->opportunities)()(DashboardOpportunityScope::OpenWindows);

    expect($windows)->toHaveCount(1)
        ->and($windows[0]->cycleId)->toBe($this->cycleId)
        ->and($windows[0]->pickupDate)->toBe('2026-11-07')
        ->and(array_map(fn ($cutoff) => [$cutoff->producerName, $cutoff->closesAt], $windows[0]->cutoffs))
        ->toBe([['Grower', '2026-11-03 18:00'], ['Baker', '2026-11-04 20:00']]);
});

it('leaves out a producer already sent or closed and a window with nobody open (R11)', function () {
    foreach ([OrderingWorld::BAKER, OrderingWorld::GROWER] as $producerId) {
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, $producerId);
    }

    expect(($this->opportunities)()(DashboardOpportunityScope::OpenWindows))->toBe([]);
});

it('leaves out a cancelled cycle (R11)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(($this->opportunities)()(DashboardOpportunityScope::OpenWindows))->toBe([]);
});

it('gives a producer with nothing sent no summary, and a producer sent only its own (S2, R11)', function () {
    expect(($this->opportunities)()(DashboardOpportunityScope::SentSummaries, OrderingWorld::BAKER))->toBe([]);

    ($this->world->setOrderLine)($this->member, $this->cycleId, $this->products[OrderingWorld::BAKER], '2');
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);

    expect(($this->opportunities)()(DashboardOpportunityScope::SentSummaries, OrderingWorld::GROWER))->toBe([])
        ->and(($this->opportunities)()(DashboardOpportunityScope::SentSummaries, OrderingWorld::BAKER))->toHaveCount(1);
});

it('gives a producer only sent cycles whose pickup is today or later in the cooperative calendar, past ones dropping off (R11)', function () {
    $this->world->clock->travelTo('2026-10-12 10:00');
    $cycles = [];
    foreach (['2026-10-17', '2026-10-24', '2026-10-31'] as $pickup) {
        $cycles[$pickup] = ($this->world->openCycle)($this->world->manager, $pickup, [OrderingWorld::BAKER]);
        ($this->world->setOrderLine)($this->member, $cycles[$pickup], $this->products[OrderingWorld::BAKER], '2');
        ($this->world->closeProducerNow)($this->world->manager, $cycles[$pickup], OrderingWorld::BAKER);
        ($this->world->sendProducerOrder)($this->world->manager, $cycles[$pickup], OrderingWorld::BAKER);
    }
    $pickups = fn () => array_map(fn ($summary) => $summary->pickupDate, ($this->opportunities)()(DashboardOpportunityScope::SentSummaries, OrderingWorld::BAKER));

    // 00:30 Madrid on the 24th is still the 23rd in UTC: the cooperative's calendar day decides.
    $this->world->clock->travelTo('2026-10-24 00:30');
    expect($pickups())->toContain('2026-10-24', '2026-10-31')->not->toContain('2026-10-17');

    $this->world->clock->travelTo('2026-10-24 23:30');
    expect($pickups())->toContain('2026-10-24');

    $this->world->clock->travelTo('2026-10-25 00:30');
    expect($pickups())->toBe(['2026-10-31']);
});
