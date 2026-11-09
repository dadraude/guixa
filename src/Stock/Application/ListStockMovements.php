<?php

namespace Guixa\Stock\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Stock\Domain\AccountNames;
use Guixa\Stock\Domain\CyclePickupDates;
use Guixa\Stock\Domain\Error\StockProductNotFound;
use Guixa\Stock\Domain\MovementType;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Support\Facades\Gate;

/** Read-only history of a stock product, computed from its immutable ledger (R28a, D26). */
final class ListStockMovements
{
    public const int PER_PAGE = 50;

    public function __construct(
        private readonly StockProductRepository $products,
        private readonly StockMovementRepository $movements,
        private readonly AccountNames $accountNames,
        private readonly CyclePickupDates $pickupDates,
    ) {}

    /** @param bool $asProducer the Producer area: only the producer's own products, whatever other roles the actor holds (R25b) */
    public function __invoke(Actor $actor, string $productId, int $page = 1, bool $asProducer = false): StockMovementPage
    {
        $product = $this->products->get($productId) ?? throw StockProductNotFound::withId($productId);

        $seesBaskets = $this->authorize($actor, $product, $asProducer);

        $newestFirst = array_reverse($this->withBalances($this->movements->forProduct($productId)));
        $pageMovements = $this->pageOf($newestFirst, max($page, 1));

        $names = $this->accountNames->forAccounts(array_values(array_unique(array_map(
            fn (array $row) => $row['movement']->recordedBy,
            $pageMovements,
        ))));
        $pickups = $this->pickupDates->forCycles(array_values(array_unique(array_filter(array_map(
            fn (array $row) => $row['movement']->cycleId,
            $pageMovements,
        )))));

        return new StockMovementPage(
            array_map(fn (array $row) => $this->toLine($row['movement'], $row['balance'], $names, $pickups, $seesBaskets), $pageMovements),
            count($newestFirst),
            $product->name(),
            $product->unit(),
            ($newestFirst[0]['balance'] ?? SignedQuantity::ofThousandths(0))->toString(),
        );
    }

    /**
     * @param  list<array{movement: StockMovement, balance: SignedQuantity}>  $newestFirst
     * @return list<array{movement: StockMovement, balance: SignedQuantity}>
     */
    private function pageOf(array $newestFirst, int $page): array
    {
        // Checked before multiplying: a huge page number would overflow the offset.
        if ($page > intdiv(count($newestFirst) + self::PER_PAGE - 1, self::PER_PAGE)) {
            return [];
        }

        return array_slice($newestFirst, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
    }

    /** @return bool whether the actor may see the baskets behind sales and corrections: only Manager and Finance (D5) */
    private function authorize(Actor $actor, StockProduct $product, bool $asProducer): bool
    {
        $gate = Gate::forUser($actor);
        if ($asProducer || ! $gate->allows('view-stock')) {
            $gate->authorize('owns', ['producer', $product->producerId]);
        }

        return $gate->allows('manage-sales');
    }

    /**
     * @param  list<StockMovement>  $oldestFirst
     * @return list<array{movement: StockMovement, balance: SignedQuantity}>
     */
    private function withBalances(array $oldestFirst): array
    {
        $balance = SignedQuantity::ofThousandths(0);
        $rows = [];

        foreach ($oldestFirst as $movement) {
            $balance = $balance->plus($movement->quantity);
            $rows[] = ['movement' => $movement, 'balance' => $balance];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $names
     * @param  array<string, string>  $pickups
     */
    private function toLine(StockMovement $movement, SignedQuantity $balance, array $names, array $pickups, bool $seesBaskets): StockMovementLine
    {
        return new StockMovementLine(
            $movement->id,
            $movement->occurredAt->format(DATE_ATOM),
            $this->displayedType($movement),
            $movement->quantity->toString(),
            $balance->toString(),
            $movement->note,
            $names[$movement->recordedBy] ?? '',
            $movement->cycleId,
            $movement->cycleId === null ? null : ($pickups[$movement->cycleId] ?? null),
            $seesBaskets ? $movement->basketId : null,
        );
    }

    private function displayedType(StockMovement $movement): string
    {
        if ($movement->type === MovementType::Adjustment && $movement->correctionId !== null) {
            return 'correction';
        }

        return $movement->type->value;
    }
}
