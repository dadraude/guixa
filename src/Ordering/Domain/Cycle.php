<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Ordering\Domain\Error\NotOnPickupWeekday;
use Guixa\Ordering\Domain\Error\PickupDateNotInFuture;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;

/** One week's round of orders, picked up on one date (R4). */
final class Cycle
{
    /** @param array<string, ProducerOrder> $producerOrders by producer id */
    private function __construct(
        public readonly string $id,
        private readonly DateTimeImmutable $pickupDate,
        private array $producerOrders,
    ) {}

    /** `$now` is read in the cooperative's timezone: "after today" is a local calendar date (R4, R4c). */
    public static function open(string $id, DateTimeImmutable $pickupDate, PickupWeekday $weekday, DateTimeImmutable $now): self
    {
        $date = $pickupDate->format('Y-m-d');

        if (! $weekday->includes($pickupDate)) {
            throw NotOnPickupWeekday::forDate($date);
        }

        if ($date <= $now->format('Y-m-d')) {
            throw PickupDateNotInFuture::forDate($date);
        }

        return new self($id, $pickupDate, []);
    }

    /** @param list<ProducerOrder> $producerOrders */
    public static function restore(string $id, DateTimeImmutable $pickupDate, array $producerOrders): self
    {
        $byProducer = [];

        foreach ($producerOrders as $producerOrder) {
            $byProducer[$producerOrder->producerId] = $producerOrder;
        }

        return new self($id, $pickupDate, $byProducer);
    }

    /**
     * The cutoff is computed once, from the rule as it is now, and never recomputed; it is null when
     * that moment has already passed (R4a, R5). A producer already in the cycle is left as it is.
     */
    public function addParticipant(string $producerId, CutoffRule $rule, DateTimeImmutable $now): void
    {
        if (isset($this->producerOrders[$producerId])) {
            return;
        }

        $cutoff = $rule->closesAt($this->pickupDate, $now->getTimezone());

        $this->producerOrders[$producerId] = ProducerOrder::participate($producerId, $cutoff > $now ? $cutoff : null);
    }

    public function pickupDate(): DateTimeImmutable
    {
        return $this->pickupDate;
    }

    /** @return list<ProducerOrder> in no particular order */
    public function producerOrders(): array
    {
        return array_values($this->producerOrders);
    }

    public function __clone()
    {
        $this->producerOrders = array_map(fn (ProducerOrder $order) => clone $order, $this->producerOrders);
    }
}
