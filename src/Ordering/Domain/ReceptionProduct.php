<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\Error\FractionalQuantityNotAllowed;
use Guixa\Ordering\Domain\ValueObject\ProportionalClaim;
use Guixa\Shared\Domain\Quantity;

/** A product in a producer's reception: the ordered and received totals and every household's allocation (R14, R17). */
final readonly class ReceptionProduct
{
    /** @param  list<ReceptionLine>  $lines  the lines not marked as not delivered */
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $variable,
        public Quantity $received,
        public array $lines,
    ) {}

    public function ordered(): Quantity
    {
        return Quantity::ofThousandths(array_sum(array_map(fn (ReceptionLine $line) => $line->ordered->thousandths, $this->lines)));
    }

    public function allocated(): Quantity
    {
        return Quantity::ofThousandths($this->allocatedThousandths());
    }

    public function nobodyOrdered(): bool
    {
        return $this->ordered()->isZero();
    }

    /** Received minus allocated, in thousandths: positive when goods are left unallocated (R17). */
    public function differenceThousandths(): int
    {
        return $this->received->thousandths - $this->allocatedThousandths();
    }

    public function isBalanced(): bool
    {
        return $this->differenceThousandths() === 0;
    }

    /** 1 unit for a non-variable product, 0.001 for a variable one (R15). */
    public function step(): Quantity
    {
        return Quantity::ofThousandths($this->variable ? 1 : 1000);
    }

    /** @return list<ProportionalClaim> the households that ordered the product (R15) */
    public function claims(): array
    {
        $claims = array_filter($this->lines, fn (ReceptionLine $line) => ! $line->ordered->isZero());

        return array_values(array_map(
            fn (ReceptionLine $line) => new ProportionalClaim($line->householdId, $line->ordered, $line->createdAt, $line->lineId),
            $claims,
        ));
    }

    /** @return list<string> the households whose allocation of this product was set by hand (R15) */
    public function manuallyAllocatedHouseholds(): array
    {
        return array_values(array_map(
            fn (ReceptionLine $line) => $line->householdId,
            array_filter($this->lines, fn (ReceptionLine $line) => $line->allocatedByHand),
        ));
    }

    /** A non-variable product is received and allocated in whole units (R9, R15, R16). */
    public function assertInStep(Quantity $quantity): void
    {
        if (! $this->variable && ! $quantity->isWhole()) {
            throw FractionalQuantityNotAllowed::forUnit($this->name, $quantity->toString());
        }
    }

    private function allocatedThousandths(): int
    {
        return array_sum(array_map(fn (ReceptionLine $line) => $line->allocated->thousandths, $this->lines));
    }
}
