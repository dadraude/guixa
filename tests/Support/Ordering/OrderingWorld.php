<?php

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use Guixa\Ordering\Application\AddParticipant;
use Guixa\Ordering\Application\AddReturnToPickup;
use Guixa\Ordering\Application\AddStockToPickup;
use Guixa\Ordering\Application\AddUnorderedProduct;
use Guixa\Ordering\Application\AdjustDeliveredQuantity;
use Guixa\Ordering\Application\CancelCycle;
use Guixa\Ordering\Application\ClearCutoff;
use Guixa\Ordering\Application\CloseProducerNow;
use Guixa\Ordering\Application\CompletePickup;
use Guixa\Ordering\Application\GetContainerCount;
use Guixa\Ordering\Application\GetCycleSummary;
use Guixa\Ordering\Application\GetHouseholdOrder;
use Guixa\Ordering\Application\GetPickup;
use Guixa\Ordering\Application\GetPickupWeekday;
use Guixa\Ordering\Application\GetProducerCycleSummary;
use Guixa\Ordering\Application\GetReception;
use Guixa\Ordering\Application\HasUnsettledLines;
use Guixa\Ordering\Application\ListCycles;
use Guixa\Ordering\Application\ListMemberCycles;
use Guixa\Ordering\Application\ListPickups;
use Guixa\Ordering\Application\OpenCycle;
use Guixa\Ordering\Application\OpenPickup;
use Guixa\Ordering\Application\ParticipateAgain;
use Guixa\Ordering\Application\PickupLocker;
use Guixa\Ordering\Application\RemoveMarkedLine;
use Guixa\Ordering\Application\RemoveReturnFromPickup;
use Guixa\Ordering\Application\RemoveStockFromPickup;
use Guixa\Ordering\Application\SendEmptyProducerOrders;
use Guixa\Ordering\Application\SendProducerOrder;
use Guixa\Ordering\Application\SetAllocation;
use Guixa\Ordering\Application\SetCutoff;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Application\SetPickupWeekday;
use Guixa\Ordering\Application\SetProductAvailability;
use Guixa\Ordering\Application\SetReceivedTotal;
use Guixa\Ordering\Application\StopParticipating;
use Guixa\Ordering\Application\StopParticipatingWhereNotSent;
use Guixa\Ordering\Domain\ProducerNotifier;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Ordering\Infrastructure\Stock\StockModuleReturnItemCatalog;
use Guixa\Ordering\Infrastructure\Stock\StockModuleStockCatalog;
use Guixa\Ordering\Infrastructure\Stock\StockModuleStockSeller;
use Guixa\Ordering\Infrastructure\Wallet\WalletModuleLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\GetReturnItem;
use Guixa\Stock\Application\GetStockOffer;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\SellStock;
use Guixa\Stock\Domain\ReturnItem;
use Guixa\Stock\Domain\StockLedger;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Wallet\Application\ChargeForPickup;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\FakeHouseholds;
use Tests\Support\InMemory\FakeProducerCutoffRules;
use Tests\Support\InMemory\FakeProductCatalog;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryCooperativeSettings;
use Tests\Support\InMemory\InMemoryCycleRepository;
use Tests\Support\InMemory\InMemoryMovementRepository;
use Tests\Support\InMemory\InMemoryOrderRepository;
use Tests\Support\InMemory\InMemoryProductAvailabilityRepository;
use Tests\Support\InMemory\InMemoryReceivedTotalRepository;
use Tests\Support\InMemory\InMemoryReturnItemRepository;
use Tests\Support\InMemory\InMemoryStockMovementRepository;
use Tests\Support\InMemory\InMemoryStockProductRepository;

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

    public readonly InMemoryReceivedTotalRepository $receivedTotals;

    public readonly FakeHouseholds $households;

    public readonly SetPickupWeekday $setPickupWeekday;

    public readonly GetPickupWeekday $getPickupWeekday;

    public readonly OpenCycle $openCycle;

    public readonly AddParticipant $addParticipant;

    public readonly StopParticipating $stopParticipating;

    public readonly ParticipateAgain $participateAgain;

    public readonly StopParticipatingWhereNotSent $stopParticipatingWhereNotSent;

    public readonly SetCutoff $setCutoff;

    public readonly CancelCycle $cancelCycle;

    public readonly CloseProducerNow $closeProducerNow;

    public readonly ClearCutoff $clearCutoff;

    public readonly ListCycles $listCycles;

    public readonly ListMemberCycles $listMemberCycles;

    public readonly SetOrderLine $setOrderLine;

    public readonly GetHouseholdOrder $getHouseholdOrder;

    public readonly RemoveMarkedLine $removeMarkedLine;

    public readonly SetProductAvailability $setProductAvailability;

    public readonly SendProducerOrder $sendProducerOrder;

    public readonly SendEmptyProducerOrders $sendEmptyProducerOrders;

    public readonly RecordingProducerNotifier $notifier;

    public readonly HasUnsettledLines $hasUnsettledLines;

    public readonly GetCycleSummary $getCycleSummary;

    public readonly GetContainerCount $getContainerCount;

    public readonly GetProducerCycleSummary $getProducerCycleSummary;

    public readonly GetReception $getReception;

    public readonly AddUnorderedProduct $addUnorderedProduct;

    public readonly SetReceivedTotal $setReceivedTotal;

    public readonly SetAllocation $setAllocation;

    public readonly GetPickup $getPickup;

    public readonly OpenPickup $openPickup;

    public readonly ListPickups $listPickups;

    public readonly AdjustDeliveredQuantity $adjustDeliveredQuantity;

    public readonly InMemoryStockProductRepository $stockProducts;

    public readonly InMemoryStockMovementRepository $stockMovements;

    public readonly InMemoryReturnItemRepository $returnItems;

    public readonly RecordStockEntry $recordStockEntry;

    public readonly AddStockToPickup $addStockToPickup;

    public readonly RemoveStockFromPickup $removeStockFromPickup;

    public readonly AddReturnToPickup $addReturnToPickup;

    public readonly RemoveReturnFromPickup $removeReturnFromPickup;

    public readonly InMemoryMovementRepository $movements;

    public readonly RecordTopUp $recordTopUp;

    public readonly CompletePickup $completePickup;

    public readonly WalletModuleLedger $walletLedger;

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
        $this->receivedTotals = new InMemoryReceivedTotalRepository;
        $this->households = new FakeHouseholds;
        $transactions = new ImmediateTransactionRunner;
        $this->notifier = new RecordingProducerNotifier;

        if ($pickupWeekday !== null) {
            $this->settings->changePickupWeekday(PickupWeekday::of($pickupWeekday));
        }

        $this->setPickupWeekday = new SetPickupWeekday($this->settings);
        $this->getPickupWeekday = new GetPickupWeekday($this->settings);
        $this->openCycle = new OpenCycle($this->cycles, $this->settings, $this->rules, $transactions, $this->clock);
        $this->addParticipant = new AddParticipant($this->cycles, $this->rules, $transactions, $this->clock);
        $this->stopParticipating = new StopParticipating($this->cycles, $transactions);
        $this->participateAgain = new ParticipateAgain($this->cycles, $this->rules, $transactions);
        $this->stopParticipatingWhereNotSent = new StopParticipatingWhereNotSent($this->cycles);
        $this->setCutoff = new SetCutoff($this->cycles, $transactions, $this->clock);
        $this->cancelCycle = new CancelCycle($this->cycles, $this->orders, $transactions, $this->clock);
        $this->closeProducerNow = new CloseProducerNow($this->cycles, $transactions, $this->clock);
        $this->clearCutoff = new ClearCutoff($this->cycles, $transactions);
        $this->listCycles = new ListCycles($this->cycles, $this->clock);
        $this->listMemberCycles = new ListMemberCycles($this->cycles, $this->orders, $this->clock);
        $this->setOrderLine = new SetOrderLine($this->cycles, $this->orders, $this->catalog, $this->availability, $transactions, $this->clock);
        $this->getHouseholdOrder = new GetHouseholdOrder($this->cycles, $this->orders, $this->catalog, $this->availability, $this->clock);
        $this->removeMarkedLine = new RemoveMarkedLine($this->cycles, $this->orders, $this->catalog, $this->availability, $transactions);
        $this->setProductAvailability = new SetProductAvailability($this->cycles, $this->catalog, $this->availability, $transactions);
        $this->sendProducerOrder = new SendProducerOrder($this->cycles, $this->orders, $this->catalog, $this->availability, $this->notifier, $transactions, $this->clock);
        $this->sendEmptyProducerOrders = new SendEmptyProducerOrders($this->cycles, $this->orders, $this->catalog, $this->availability, $transactions, $this->clock);
        $this->hasUnsettledLines = new HasUnsettledLines($this->orders);
        $this->getCycleSummary = new GetCycleSummary($this->cycles, $this->orders, $this->households);
        $this->getContainerCount = new GetContainerCount($this->cycles, $this->orders, $this->households, $this->catalog, $this->availability);
        $this->getProducerCycleSummary = new GetProducerCycleSummary($this->cycles, $this->orders, $this->catalog, $this->availability);
        $receptions = new ReceptionLoader($this->orders, $this->catalog, $this->availability, $this->receivedTotals);
        $this->getReception = new GetReception($this->cycles, $receptions, $this->households);
        $this->addUnorderedProduct = new AddUnorderedProduct($this->cycles, $receptions, $this->receivedTotals, $transactions);
        $this->setAllocation = new SetAllocation($this->cycles, $this->orders, $receptions, $this->households, $transactions, $this->clock);

        $this->getPickup = new GetPickup($this->cycles, $this->orders, $this->households);
        $this->listPickups = new ListPickups($this->cycles, $this->orders, $this->households);
        $this->openPickup = new OpenPickup($this->cycles, $this->orders, $this->households, $transactions);
        $this->adjustDeliveredQuantity = new AdjustDeliveredQuantity($this->cycles, $this->orders, $transactions);

        $this->manager = new Actor('00000000-0000-7000-8000-00000000000a', [Role::Manager]);

        // Stock is the real Stock module over in-memory repositories, reached through Ordering's adapters.
        $this->stockProducts = new InMemoryStockProductRepository;
        $this->stockMovements = new InMemoryStockMovementRepository;
        $this->returnItems = new InMemoryReturnItemRepository;
        $stockCatalog = new StockModuleStockCatalog(new GetStockOffer($this->stockProducts, $this->stockMovements));
        $returnItemCatalog = new StockModuleReturnItemCatalog(new GetReturnItem($this->returnItems));
        $this->recordStockEntry = new RecordStockEntry($this->stockProducts, $this->stockMovements, new StockLedger($this->stockMovements), $transactions, $this->clock);
        $pickups = new PickupLocker($this->cycles, $this->orders);
        $this->addStockToPickup = new AddStockToPickup($pickups, $this->orders, $stockCatalog, $transactions);
        $this->removeStockFromPickup = new RemoveStockFromPickup($pickups, $this->orders, $transactions);
        $this->addReturnToPickup = new AddReturnToPickup($pickups, $this->orders, $returnItemCatalog, $transactions);
        $this->removeReturnFromPickup = new RemoveReturnFromPickup($pickups, $this->orders, $transactions);

        // Wallet is the real Wallet module over an in-memory repository, reached through Ordering's adapters.
        $this->movements = new InMemoryMovementRepository;
        $this->recordTopUp = new RecordTopUp($this->movements, $this->clock);
        $stockSeller = new StockModuleStockSeller(
            new SellStock($this->stockProducts, $this->stockMovements, new StockLedger($this->stockMovements), $transactions, $this->clock),
            new GetStockOffer($this->stockProducts, $this->stockMovements),
        );
        $walletLedger = new WalletModuleLedger($this->recordTopUp, new ChargeForPickup($this->movements, $this->clock),
            new RecordCorrection($this->movements, $this->clock),
            new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 3).'/lang'), 'ca'));
        $this->walletLedger = $walletLedger;
        $this->setReceivedTotal = new SetReceivedTotal($this->cycles, $this->orders, $receptions, $this->receivedTotals, $transactions, $walletLedger, $this->clock);
        $this->completePickup = new CompletePickup($pickups, $this->orders, $stockSeller, $walletLedger, $transactions, $this->clock);

        // Both producers have an account, so their orders can be sent.
        $this->notifier->hasAccount(self::BAKER, self::GROWER);

        // The baker's default cutoff is 3 days before pickup at 20:00 (S6).
        $this->rules->activate(self::BAKER, 3, '20:00');
        $this->rules->activate(self::GROWER, 2, '12:00');
    }

    /** @return string the new stock product's id, with `$available` on hand */
    public function stockProduct(string $name, int $priceCents, string $available, bool $variable = false, string $unit = 'jar', ?string $containersPerUnit = null): string
    {
        $product = StockProduct::create($this->stockProducts->nextId(), $name, $unit, Money::ofCents($priceCents), $variable, $containersPerUnit);
        $this->stockProducts->add($product);
        ($this->recordStockEntry)($this->manager, $product->id, $available, null);

        return $product->id;
    }

    public function deactivateStockProduct(string $id): void
    {
        $product = $this->stockProducts->get($id);
        $product->deactivate();
        $this->stockProducts->add($product);
    }

    /** @return string the new return item's id */
    public function returnItem(string $name, int $amountCents): string
    {
        $item = ReturnItem::create($this->returnItems->nextId(), $name, Money::ofCents($amountCents));
        $this->returnItems->add($item);

        return $item->id;
    }

    public function deactivateReturnItem(string $id): void
    {
        $item = $this->returnItems->get($id);
        $item->deactivate();
        $this->returnItems->add($item);
    }

    public function member(): Actor
    {
        return new Actor('00000000-0000-7000-8000-0000000000cc', [Role::Member], '00000000-0000-7000-8000-0000000000dd');
    }
}

/** What the producer notifier would have emailed, kept for the tests to read. */
final class RecordingProducerNotifier implements ProducerNotifier
{
    /** @var list<array{producerId: string, pickupDate: string, totals: list<array{name: string, unit: string, quantity: string}>}> */
    public array $notified = [];

    /** @var array<string, true> */
    private array $accounts = [];

    public function hasAccount(string ...$producerIds): void
    {
        foreach ($producerIds as $producerId) {
            $this->accounts[$producerId] = true;
        }
    }

    public function withoutAccount(string $producerId): void
    {
        unset($this->accounts[$producerId]);
    }

    public function canNotify(string $producerId): bool
    {
        return isset($this->accounts[$producerId]);
    }

    public function notify(string $producerId, DateTimeImmutable $pickupDate, array $totals): void
    {
        $this->notified[] = [
            'producerId' => $producerId,
            'pickupDate' => $pickupDate->format('Y-m-d'),
            'totals' => array_map(fn (array $total) => ['name' => $total['name'], 'unit' => $total['unit'], 'quantity' => $total['quantity']->toString()], $totals),
        ];
    }
}
