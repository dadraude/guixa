<?php

use Guixa\Ordering\Domain\Error\CycleCancelled;
use Guixa\Ordering\Domain\Error\LineNotMarked;
use Guixa\Ordering\Domain\Error\ProducerClosed;
use Guixa\Ordering\Domain\Error\ProducerHasNoAccount;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerNotParticipating;
use Guixa\Ordering\Domain\Error\ProducerOrderSent;
use Guixa\Ordering\Domain\Error\ProducerStillOpen;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Tests\Support\Ordering\OrderingWorld;

const SEND_ANNA = '00000000-0000-7000-8000-0000000000a1';
const SEND_BIEL = '00000000-0000-7000-8000-0000000000a2';
const SEND_HOUSEHOLD_H = '00000000-0000-7000-8000-0000000000e1';
const SEND_HOUSEHOLD_J = '00000000-0000-7000-8000-0000000000e2';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup is Saturday 7 November and the baker's cutoff Wednesday 4 November 20:00.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->jam = $this->world->catalog->offer(OrderingWorld::BAKER, 'Jam', 'jar', 400, variable: false);
    $this->apples = $this->world->catalog->offer(OrderingWorld::GROWER, 'Apples', 'kg', 300, variable: true);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->anna = Actor::memberOf(SEND_ANNA, SEND_HOUSEHOLD_H);
    $this->biel = Actor::memberOf(SEND_BIEL, SEND_HOUSEHOLD_J);
    $this->order = fn (Actor $member, string $productId, string $quantity) => ($this->world->setOrderLine)($member, $this->cycleId, $productId, $quantity);
    $this->closeBaker = fn () => ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $this->send = fn (string $producerId = OrderingWorld::BAKER) => ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, $producerId);
    $this->sendEmpty = fn () => ($this->world->sendEmptyProducerOrders)();
    $this->storedMarks = fn (string $householdId) => $this->world->orders->notDeliveredProductIds(
        $this->world->orders->ofHousehold($this->cycleId, $householdId)->id,
    );
});

// R12a — sending

it('notifies the producer with the total quantity per product and no household names (R12a)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->order)($this->biel, $this->bread, '3');
    ($this->order)($this->biel, $this->jam, '1');
    ($this->closeBaker)();

    ($this->send)();

    expect($this->world->notifier->notified)->toBe([[
        'producerId' => OrderingWorld::BAKER,
        'pickupDate' => '2026-11-07',
        'totals' => [
            ['name' => 'Jam', 'unit' => 'jar', 'quantity' => '1.000'],
            ['name' => 'Loaf', 'unit' => 'loaf', 'quantity' => '5.000'],
        ],
    ]]);
});

it('leaves lines marked will not be delivered out of the sent order and stores the mark (R3b, R12a, S32)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->order)($this->anna, $this->jam, '2');
    ($this->order)($this->biel, $this->jam, '1');
    ($this->closeBaker)();
    $this->world->catalog->deactivate($this->jam);

    ($this->send)();

    expect($this->world->notifier->notified[0]['totals'])->toBe([['name' => 'Loaf', 'unit' => 'loaf', 'quantity' => '2.000']])
        ->and(($this->storedMarks)(SEND_HOUSEHOLD_H))->toBe([$this->jam])
        ->and(($this->storedMarks)(SEND_HOUSEHOLD_J))->toBe([$this->jam]);
});

it('keeps the stored mark when the product becomes orderable again after the send (D9)', function () {
    ($this->order)($this->anna, $this->jam, '2');
    ($this->closeBaker)();
    $this->world->catalog->deactivate($this->jam);
    ($this->send)();

    $this->world->catalog->reactivate($this->jam);

    expect(($this->storedMarks)(SEND_HOUSEHOLD_H))->toBe([$this->jam]);
});

it('stores no mark on lines of other producers or on lines that are delivered (D9)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->order)($this->anna, $this->apples, '1.500');
    ($this->closeBaker)();

    ($this->send)();

    expect(($this->storedMarks)(SEND_HOUSEHOLD_H))->toBe([]);
});

it('sends a producer order only once (R12a)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->send)();

    expect(fn () => ($this->send)())->toThrow(ProducerOrderSent::class)
        ->and($this->world->notifier->notified)->toHaveCount(1);
});

it('rejects sending a producer that is still open or no longer participates (R6c, R12a, S36)', function () {
    ($this->order)($this->anna, $this->apples, '1');
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->closeBaker)();

    expect(fn () => ($this->send)(OrderingWorld::GROWER))->toThrow(ProducerStillOpen::class)
        ->and(fn () => ($this->send)(OrderingWorld::BAKER))->toThrow(ProducerNotParticipating::class)
        ->and($this->world->notifier->notified)->toBe([]);
});

it('rejects sending for a producer outside the cycle or a cancelled cycle (R12a)', function () {
    $otherProducer = '00000000-0000-7000-8000-0000000000b9';

    expect(fn () => ($this->send)($otherProducer))->toThrow(ProducerNotInCycle::class);

    ($this->closeBaker)();
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(fn () => ($this->send)())->toThrow(CycleCancelled::class);
});

it('refuses to send when the producer has no account and sends once it has one (R12a)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    $this->world->notifier->withoutAccount(OrderingWorld::BAKER);

    expect(fn () => ($this->send)())->toThrow(ProducerHasNoAccount::class)
        ->and($this->world->notifier->notified)->toBe([])
        ->and(($this->storedMarks)(SEND_HOUSEHOLD_H))->toBe([]);

    $this->world->notifier->hasAccount(OrderingWorld::BAKER);
    ($this->send)();

    expect($this->world->notifier->notified)->toHaveCount(1);
});

it('lets only a manager send a producer order (R12a)', function () {
    ($this->closeBaker)();

    expect(fn () => ($this->world->sendProducerOrder)($this->anna, $this->cycleId, OrderingWorld::BAKER))->toThrow(NotAllowed::class)
        ->and($this->world->notifier->notified)->toBe([]);
});

// R6b, S34 — cutoff frozen once sent

it('refuses to move, close or clear the cutoff once the order is sent (R6b, S34)', function () {
    ($this->closeBaker)();
    ($this->send)();
    $manager = $this->world->manager;

    expect(fn () => ($this->world->setCutoff)($manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00'))->toThrow(ProducerOrderSent::class)
        ->and(fn () => ($this->world->closeProducerNow)($manager, $this->cycleId, OrderingWorld::BAKER))->toThrow(ProducerOrderSent::class)
        ->and(fn () => ($this->world->clearCutoff)($manager, $this->cycleId, OrderingWorld::BAKER))->toThrow(ProducerOrderSent::class);
});

it('still moves the cutoff of a producer whose order is not sent while another is sent (R6, S34)', function () {
    ($this->closeBaker)();
    ($this->send)();

    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::GROWER, '2026-11-05T12:00');

    $grower = collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $this->cycleId)->participants;
    expect(collect($grower)->firstWhere('producerId', OrderingWorld::GROWER)->cutoff)->toBe('2026-11-05T12:00:00+01:00');
});

it('rejects line changes of a producer once its order is sent, even before its cutoff (R8)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->send)();
    // The cutoff is the moment of closing; a clock before it would make the producer open if only the cutoff counted.
    $this->world->clock->travelTo('2026-10-22 09:59');

    expect(fn () => ($this->order)($this->anna, $this->bread, '3'))->toThrow(ProducerClosed::class)
        ->and(fn () => ($this->order)($this->anna, $this->bread, '0'))->toThrow(ProducerClosed::class);
});

it('shows a sent producer as not open (R6a)', function () {
    ($this->closeBaker)();
    ($this->send)();

    $participants = collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $this->cycleId)->participants;

    expect(collect($participants)->firstWhere('producerId', OrderingWorld::BAKER)->open)->toBeFalse();
});

// R4b, S37 — cancel once sent

it('rejects cancelling a cycle once one producer order is sent and keeps its orders (R4b, S37)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->send)();

    expect(fn () => ($this->world->cancelCycle)($this->world->manager, $this->cycleId))->toThrow(ProducerOrderSent::class)
        ->and($this->world->orders->ofHousehold($this->cycleId, SEND_HOUSEHOLD_H))->not->toBeNull()
        ->and(collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $this->cycleId)->cancelled)->toBeFalse();
});

// R6c, R3a, S50 — participation of a sent order

it('refuses to stop participating or participate again once the order is sent (R6c)', function () {
    ($this->closeBaker)();
    ($this->send)();

    expect(fn () => ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::BAKER))->toThrow(ProducerOrderSent::class)
        ->and(fn () => ($this->world->participateAgain)($this->world->manager, $this->cycleId, OrderingWorld::BAKER))->toThrow(ProducerOrderSent::class);
});

it('leaves a sent order untouched when its producer is deactivated (R3a, S50)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->send)();
    $laterCycle = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);

    ($this->world->stopParticipatingWhereNotSent)(OrderingWorld::BAKER);

    $cycles = collect(($this->world->listCycles)($this->world->manager));
    expect($cycles->firstWhere('id', $this->cycleId)->participants[0]->participating)->toBeTrue()
        ->and($cycles->firstWhere('id', $laterCycle)->participants[0]->participating)->toBeFalse();
});

// R12a, D10 — SendEmptyProducerOrders sends only

it('sends a closed producer with no line without notifying anyone (R12a, D10)', function () {
    ($this->closeBaker)();

    ($this->sendEmpty)();

    expect($this->world->notifier->notified)->toBe([])
        ->and(fn () => ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00'))->toThrow(ProducerOrderSent::class);
});

it('sends a producer with no account when it has no line to send (R12a, D10)', function () {
    $this->world->notifier->withoutAccount(OrderingWorld::BAKER);
    ($this->closeBaker)();

    ($this->sendEmpty)();

    expect(fn () => ($this->send)())->toThrow(ProducerOrderSent::class);
});

it('sends a producer whose only lines are marked will not be delivered and stores the marks (R3b, R12a, D10)', function () {
    ($this->order)($this->anna, $this->jam, '2');
    ($this->closeBaker)();
    $this->world->catalog->deactivate($this->jam);

    ($this->sendEmpty)();

    expect($this->world->notifier->notified)->toBe([])
        ->and(($this->storedMarks)(SEND_HOUSEHOLD_H))->toBe([$this->jam])
        ->and(fn () => ($this->send)())->toThrow(ProducerOrderSent::class);
});

it('leaves open producers, producers with lines to send and producers that do not participate unsent (R6c, R12a, D10)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::GROWER);

    ($this->sendEmpty)();

    // Baker has a line to send, the grower does not participate: neither is sent.
    expect(fn () => ($this->send)(OrderingWorld::GROWER))->toThrow(ProducerNotParticipating::class);
    ($this->send)();
    expect($this->world->notifier->notified)->toHaveCount(1);
});

it('sends an empty producer only when its cutoff passes (R6a, D10)', function () {
    ($this->sendEmpty)();
    expect(fn () => ($this->send)(OrderingWorld::GROWER))->toThrow(ProducerStillOpen::class);

    // The grower has no cutoff passed yet; at the baker's cutoff (Wednesday 4 November 20:00) only the baker is closed.
    $this->world->clock->travelTo('2026-11-04 20:00');
    ($this->sendEmpty)();

    expect(fn () => ($this->send)(OrderingWorld::BAKER))->toThrow(ProducerOrderSent::class)
        ->and(fn () => ($this->send)(OrderingWorld::GROWER))->toThrow(ProducerStillOpen::class);
});

it('skips cancelled cycles and sends nothing twice (D10)', function () {
    ($this->closeBaker)();
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    ($this->sendEmpty)();
    ($this->sendEmpty)();

    expect(fn () => ($this->send)())->toThrow(CycleCancelled::class);
});

// R3c — availability is frozen once sent

it('refuses to mark a product unavailable or available once its producer order is sent (R3c)', function () {
    ($this->closeBaker)();
    ($this->send)();
    $baker = Actor::producer('00000000-0000-7000-8000-0000000000aa', OrderingWorld::BAKER);

    expect(fn () => ($this->world->setProductAvailability)($baker, $this->cycleId, $this->bread, false))->toThrow(ProducerOrderSent::class)
        ->and(fn () => ($this->world->setProductAvailability)($baker, $this->cycleId, $this->bread, true))->toThrow(ProducerOrderSent::class);
});

it('still lets a producer whose order is not sent mark its products while another is sent (R3c)', function () {
    ($this->closeBaker)();
    ($this->send)();
    $grower = Actor::producer('00000000-0000-7000-8000-0000000000ab', OrderingWorld::GROWER);

    ($this->world->setProductAvailability)($grower, $this->cycleId, $this->apples, false);

    expect($this->world->availability->unavailableIn($this->cycleId))->toBe([$this->apples]);
});

// D9 — the stored mark is what counts once sent

it('keeps a line marked after the send when its product becomes orderable again (R3b, D9)', function () {
    ($this->order)($this->anna, $this->jam, '2');
    ($this->closeBaker)();
    $this->world->catalog->deactivate($this->jam);
    ($this->send)();

    $this->world->catalog->reactivate($this->jam);

    $lines = collect(($this->world->getHouseholdOrder)($this->anna, $this->cycleId)->lines);
    expect($lines->firstWhere('productId', $this->jam)->notDelivered)->toBeTrue();
});

it('keeps a delivered line unmarked after the send when its product is deactivated later (R3b, D9)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->closeBaker)();
    ($this->send)();

    $this->world->catalog->deactivate($this->bread);

    $lines = collect(($this->world->getHouseholdOrder)($this->anna, $this->cycleId)->lines);
    expect($lines->firstWhere('productId', $this->bread)->notDelivered)->toBeFalse();
});

it('refuses to remove a sent line that was never marked and removes a stored marked one (R3b, R8, D9)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->order)($this->anna, $this->jam, '1');
    ($this->closeBaker)();
    $this->world->catalog->deactivate($this->jam);
    ($this->send)();
    $this->world->catalog->deactivate($this->bread);

    expect(fn () => ($this->world->removeMarkedLine)($this->anna, $this->cycleId, $this->bread))->toThrow(LineNotMarked::class);

    ($this->world->removeMarkedLine)($this->anna, $this->cycleId, $this->jam);

    $lines = collect(($this->world->getHouseholdOrder)($this->anna, $this->cycleId)->lines)->pluck('productId')->all();
    expect($lines)->toBe([$this->bread]);
});

it('rejects line changes before a moved cutoff once the order is sent, for setting and removing (R6b, R8)', function () {
    ($this->order)($this->anna, $this->bread, '2');
    ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-03T12:00');
    $this->world->clock->travelTo('2026-11-03 12:01');
    ($this->send)();

    $this->world->clock->travelTo('2026-11-03 11:59');

    expect(fn () => ($this->order)($this->anna, $this->bread, '3'))->toThrow(ProducerClosed::class)
        ->and(fn () => ($this->order)($this->anna, $this->bread, '0'))->toThrow(ProducerClosed::class)
        ->and(fn () => ($this->order)($this->anna, $this->jam, '1'))->toThrow(ProducerClosed::class);
});
