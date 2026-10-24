<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;

/** A producer's part of a cycle: whether it participates and when it closes (R4a, R5). */
final class ProducerOrder
{
    private function __construct(
        public readonly string $producerId,
        private bool $participating,
        private ?DateTimeImmutable $cutoff,
        private ?DateTimeImmutable $sentAt = null,
        private ?DateTimeImmutable $validatedAt = null,
        private ?DateTimeImmutable $distributedAt = null,
    ) {}

    /** A producer that starts participating; a null cutoff means its moment had already passed (R5). */
    public static function participate(string $producerId, ?DateTimeImmutable $cutoff): self
    {
        return new self($producerId, true, $cutoff);
    }

    public static function restore(
        string $producerId,
        bool $participating,
        ?DateTimeImmutable $cutoff,
        ?DateTimeImmutable $sentAt = null,
        ?DateTimeImmutable $validatedAt = null,
        ?DateTimeImmutable $distributedAt = null,
    ): self {
        return new self($producerId, $participating, $cutoff, $sentAt, $validatedAt, $distributedAt);
    }

    public function isParticipating(): bool
    {
        return $this->participating;
    }

    public function stopParticipating(): void
    {
        $this->participating = false;
    }

    /** The cutoff is kept as it was: the producer does not get a new one (R5). */
    public function participateAgain(): void
    {
        $this->participating = true;
    }

    public function changeCutoff(?DateTimeImmutable $cutoff): void
    {
        $this->cutoff = $cutoff;
    }

    public function cutoff(): ?DateTimeImmutable
    {
        return $this->cutoff;
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(DateTimeImmutable $now): void
    {
        $this->sentAt = $now;
    }

    public function isValidated(): bool
    {
        return $this->validatedAt !== null;
    }

    public function validatedAt(): ?DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function validate(DateTimeImmutable $now): void
    {
        $this->validatedAt = $now;
    }

    public function clearValidation(): void
    {
        $this->validatedAt = null;
    }

    public function isDistributed(): bool
    {
        return $this->distributedAt !== null;
    }

    public function distributedAt(): ?DateTimeImmutable
    {
        return $this->distributedAt;
    }

    public function distribute(DateTimeImmutable $now): void
    {
        $this->distributedAt = $now;
    }

    /** The validation stays: only changing a received total clears it (R17a). */
    public function reopen(): void
    {
        $this->distributedAt = null;
    }

    /**
     * The producer's marked lines to store as not delivered once sent (D9), by order id.
     *
     * @param  list<Order>  $orders
     * @param  array<string, array<string, true>>  $marksByOrder
     * @return array<string, list<string>> product ids per order id; orders without a marked line of this producer are left out
     */
    public function markedLines(array $orders, array $marksByOrder): array
    {
        $marked = [];

        foreach ($orders as $order) {
            foreach ($order->lines() as $line) {
                if ($line->producerId === $this->producerId && isset($marksByOrder[$order->id][$line->productId])) {
                    $marked[$order->id][] = $line->productId;
                }
            }
        }

        return $marked;
    }

    /**
     * What the producer has to deliver: the ordered quantity per product over every household's lines, leaving out
     * the marked ones (R3b, R12a). Sorted by product name.
     *
     * @param  list<Order>  $orders  the cycle's orders
     * @param  array<string, array<string, true>>  $marksByOrder  marked product ids per order id (`NotDeliveredMarks::of`)
     * @return list<array{productId: string, name: string, unit: string, quantity: Quantity}>
     */
    public function totalsToSend(array $orders, array $marksByOrder): array
    {
        $totals = [];

        foreach ($orders as $order) {
            foreach ($order->orderedLines() as $line) {
                if ($line->producerId !== $this->producerId || isset($marksByOrder[$order->id][$line->productId])) {
                    continue;
                }

                $total = $totals[$line->productId] ?? ['productId' => $line->productId, 'name' => $line->name, 'unit' => $line->unit, 'quantity' => Quantity::ofThousandths(0)];
                $total['quantity'] = Quantity::ofThousandths($total['quantity']->thousandths + $line->ordered->thousandths);
                $totals[$line->productId] = $total;
            }
        }

        usort($totals, fn (array $a, array $b) => [$a['name'], $a['productId']] <=> [$b['name'], $b['productId']]);

        return $totals;
    }
}
