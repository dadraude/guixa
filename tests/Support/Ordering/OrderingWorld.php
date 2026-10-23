<?php

namespace Tests\Support\Ordering;

use Guixa\Ordering\Application\AddParticipant;
use Guixa\Ordering\Application\ClearCutoff;
use Guixa\Ordering\Application\CloseProducerNow;
use Guixa\Ordering\Application\GetHouseholdOrder;
use Guixa\Ordering\Application\GetPickupWeekday;
use Guixa\Ordering\Application\HasUnsettledLines;
use Guixa\Ordering\Application\ListCycles;
use Guixa\Ordering\Application\OpenCycle;
use Guixa\Ordering\Application\RemoveMarkedLine;
use Guixa\Ordering\Application\SetCutoff;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Application\SetPickupWeekday;
use Guixa\Ordering\Application\SetProductAvailability;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\FakeProducerCutoffRules;
use Tests\Support\InMemory\FakeProductCatalog;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryCooperativeSettings;
use Tests\Support\InMemory\InMemoryCycleRepository;
use Tests\Support\InMemory\InMemoryOrderRepository;
use Tests\Support\InMemory\InMemoryProductAvailabilityRepository;

/** The Ordering use cases wired to in-memory adapters, for the Application tests. */
final class OrderingWorld
{
    public const BAKER = '00000000-0000-7000-8000-0000000000b1';

    public const GROWER = '00000000-0000-7000-8000-0000000000b2';

    public readonly FrozenClock $clock;

    public readonly InMemoryCycleRepository $cycles;

    public readonly InMemoryCooperativeSettings $settings;

    public readonly FakeProducerCutoffRules $rules;

    public readonly InMemoryOrderRepository $orders;

    public readonly FakeProductCatalog $catalog;

    public readonly InMemoryProductAvailabilityRepository $availability;

    public readonly SetPickupWeekday $setPickupWeekday;

    public readonly GetPickupWeekday $getPickupWeekday;

    public readonly OpenCycle $openCycle;

    public readonly AddParticipant $addParticipant;

    public readonly SetCutoff $setCutoff;

    public readonly CloseProducerNow $closeProducerNow;

    public readonly ClearCutoff $clearCutoff;

    public readonly ListCycles $listCycles;

    public readonly SetOrderLine $setOrderLine;

    public readonly GetHouseholdOrder $getHouseholdOrder;

    public readonly RemoveMarkedLine $removeMarkedLine;

    public readonly SetProductAvailability $setProductAvailability;

    public readonly HasUnsettledLines $hasUnsettledLines;

    public readonly Actor $manager;

    /** @param  ?int  $pickupWeekday  the cooperative's weekday (ISO), or null while a manager has not chosen one (R4c) */
    public function __construct(string $now = '2026-10-22 10:00', ?int $pickupWeekday = 6)
    {
        $this->clock = new FrozenClock($now);
        $this->cycles = new InMemoryCycleRepository;
        $this->settings = new InMemoryCooperativeSettings;
        $this->rules = new FakeProducerCutoffRules;
        $this->orders = new InMemoryOrderRepository;
        $this->catalog = new FakeProductCatalog;
        $this->availability = new InMemoryProductAvailabilityRepository;
        $transactions = new ImmediateTransactionRunner;

        if ($pickupWeekday !== null) {
            $this->settings->changePickupWeekday(PickupWeekday::of($pickupWeekday));
        }

        $this->setPickupWeekday = new SetPickupWeekday($this->settings);
        $this->getPickupWeekday = new GetPickupWeekday($this->settings);
        $this->openCycle = new OpenCycle($this->cycles, $this->settings, $this->rules, $transactions, $this->clock);
        $this->addParticipant = new AddParticipant($this->cycles, $this->rules, $transactions, $this->clock);
        $this->setCutoff = new SetCutoff($this->cycles, $transactions, $this->clock);
        $this->closeProducerNow = new CloseProducerNow($this->cycles, $transactions, $this->clock);
        $this->clearCutoff = new ClearCutoff($this->cycles, $transactions);
        $this->listCycles = new ListCycles($this->cycles, $this->clock);
        $this->setOrderLine = new SetOrderLine($this->cycles, $this->orders, $this->catalog, $this->availability, $transactions, $this->clock);
        $this->getHouseholdOrder = new GetHouseholdOrder($this->cycles, $this->orders, $this->catalog, $this->availability, $this->clock);
        $this->removeMarkedLine = new RemoveMarkedLine($this->cycles, $this->orders, $this->catalog, $this->availability, $transactions);
        $this->setProductAvailability = new SetProductAvailability($this->cycles, $this->catalog, $this->availability, $transactions);
        $this->hasUnsettledLines = new HasUnsettledLines($this->orders);

        $this->manager = new Actor('00000000-0000-7000-8000-00000000000a', [Role::Manager]);

        // The baker's default cutoff is 3 days before pickup at 20:00 (S6).
        $this->rules->activate(self::BAKER, 3, '20:00');
        $this->rules->activate(self::GROWER, 2, '12:00');
    }

    public function member(): Actor
    {
        return new Actor('00000000-0000-7000-8000-0000000000cc', [Role::Member], '00000000-0000-7000-8000-0000000000dd');
    }
}
