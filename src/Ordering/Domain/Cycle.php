<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Ordering\Domain\Error\CutoffNotInFuture;
use Guixa\Ordering\Domain\Error\CutoffOutsideWindow;
use Guixa\Ordering\Domain\Error\NotOnPickupWeekday;
use Guixa\Ordering\Domain\Error\PickupDateNotInFuture;
use Guixa\Ordering\Domain\Error\ProducerClosed;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerNotParticipating;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Shared\Domain\LocalTime;

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

    /**
     * Moves the cutoff to a future moment from 6 days before the pickup date at 00:00 up to, not including,
     * the pickup date at 00:00; it works even if the current cutoff has passed (R6).
     */
    public function setCutoff(string $producerId, DateTimeImmutable $at, DateTimeImmutable $now): void
    {
        $producerOrder = $this->producerOrderOf($producerId);

        if ($at <= $now) {
            throw CutoffNotInFuture::at($at->format('c'));
        }

        $timezone = $now->getTimezone();
        $earliest = CutoffRule::of(6, '00:00')->closesAt($this->pickupDate, $timezone);

        if ($at < $earliest || $at >= $this->closesWithoutCutoff($timezone)) {
            throw CutoffOutsideWindow::at($at->format('c'), $this->pickupDate->format('Y-m-d'));
        }

        $producerOrder->changeCutoff($at);
    }

    public function closeNow(string $producerId, DateTimeImmutable $now): void
    {
        $this->producerOrderOf($producerId)->changeCutoff($now);
    }

    public function clearCutoff(string $producerId): void
    {
        $this->producerOrderOf($producerId)->changeCutoff(null);
    }

    /** Open until its cutoff, or until the pickup date at 00:00 when it has none (R6a). */
    public function isOpenFor(string $producerId, DateTimeImmutable $now): bool
    {
        $producerOrder = $this->producerOrders[$producerId] ?? null;

        if ($producerOrder === null || ! $producerOrder->isParticipating()) {
            return false;
        }

        return $now < ($producerOrder->cutoff() ?? $this->closesWithoutCutoff($now->getTimezone()));
    }

    /** What a line change needs: the producer takes part in the cycle and is open (R3, R8). */
    public function assertOpenFor(string $producerId, DateTimeImmutable $now): void
    {
        if (! ($this->producerOrders[$producerId] ?? null)?->isParticipating()) {
            throw ProducerNotParticipating::withId($producerId);
        }

        if (! $this->isOpenFor($producerId, $now)) {
            throw ProducerClosed::withId($producerId);
        }
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

    private function producerOrderOf(string $producerId): ProducerOrder
    {
        return $this->producerOrders[$producerId] ?? throw ProducerNotInCycle::withId($this->id, $producerId);
    }

    private function closesWithoutCutoff(DateTimeZone $timezone): DateTimeImmutable
    {
        return LocalTime::at($this->pickupDate, '00:00', $timezone);
    }

    public function __clone()
    {
        $this->producerOrders = array_map(fn (ProducerOrder $order) => clone $order, $this->producerOrders);
    }
}
