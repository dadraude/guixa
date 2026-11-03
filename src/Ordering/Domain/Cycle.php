<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Ordering\Domain\Error\CutoffNotInFuture;
use Guixa\Ordering\Domain\Error\CutoffOutsideWindow;
use Guixa\Ordering\Domain\Error\CycleCancelled;
use Guixa\Ordering\Domain\Error\CycleClosed;
use Guixa\Ordering\Domain\Error\NotOnPickupWeekday;
use Guixa\Ordering\Domain\Error\PickupDateNotInFuture;
use Guixa\Ordering\Domain\Error\ProducerClosed;
use Guixa\Ordering\Domain\Error\ProducerDistributed;
use Guixa\Ordering\Domain\Error\ProducerNotDistributed;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerNotParticipating;
use Guixa\Ordering\Domain\Error\ProducerOrderNotSent;
use Guixa\Ordering\Domain\Error\ProducerOrderSent;
use Guixa\Ordering\Domain\Error\ProducerStillOpen;
use Guixa\Ordering\Domain\Error\ReceptionNotValidated;
use Guixa\Ordering\Domain\Error\ReceptionUnbalanced;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Shared\Domain\LocalTime;
use Guixa\Shared\Domain\Quantity;

/** One week's round of orders, picked up on one date (R4). */
final class Cycle
{
    /** @param array<string, ProducerOrder> $producerOrders by producer id */
    private function __construct(
        public readonly string $id,
        private readonly DateTimeImmutable $pickupDate,
        private array $producerOrders,
        private ?DateTimeImmutable $cancelledAt = null,
        private ?DateTimeImmutable $settledAt = null,
        private ?DateTimeImmutable $closedAt = null,
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
    public static function restore(string $id, DateTimeImmutable $pickupDate, array $producerOrders, ?DateTimeImmutable $cancelledAt = null, ?DateTimeImmutable $settledAt = null, ?DateTimeImmutable $closedAt = null): self
    {
        $byProducer = [];

        foreach ($producerOrders as $producerOrder) {
            $byProducer[$producerOrder->producerId] = $producerOrder;
        }

        return new self($id, $pickupDate, $byProducer, $cancelledAt, $settledAt, $closedAt);
    }

    /**
     * The cutoff is computed once, from the rule as it is now, and never recomputed; it is null when
     * that moment has already passed (R4a, R5). A producer already in the cycle is left as it is.
     */
    public function addParticipant(string $producerId, CutoffRule $rule, DateTimeImmutable $now): void
    {
        $this->assertNotCancelled();

        if (isset($this->producerOrders[$producerId])) {
            return;
        }

        $cutoff = $rule->closesAt($this->pickupDate, $now->getTimezone());

        $this->producerOrders[$producerId] = ProducerOrder::participate($producerId, $cutoff > $now ? $cutoff : null);
    }

    /** Lines of the producer stay and show as *will not be delivered*; a sent order is refused (R6c). */
    public function stopParticipating(string $producerId): void
    {
        $this->assertNotCancelled();
        $this->unsentProducerOrderOf($producerId)->stopParticipating();
    }

    public function participateAgain(string $producerId): void
    {
        $this->assertNotCancelled();
        $this->unsentProducerOrderOf($producerId)->participateAgain();
    }

    /**
     * Sends the producer's order: it participates, is closed and was not sent; from then on its cutoff is frozen
     * (R6b, R12a, S36).
     */
    public function send(string $producerId, DateTimeImmutable $now): void
    {
        $this->assertNotCancelled();
        $producerOrder = $this->unsentProducerOrderOf($producerId);

        if (! $producerOrder->isParticipating()) {
            throw ProducerNotParticipating::withId($producerId);
        }

        if ($this->isOpenFor($producerId, $now)) {
            throw ProducerStillOpen::withId($producerId);
        }

        $producerOrder->markSent($now);
    }

    /** Whether the producer takes part, is closed and has not been sent: what the scheduler sends when it has no line (D10). */
    public function isReadyToSend(string $producerId, DateTimeImmutable $now): bool
    {
        $producerOrder = $this->producerOrders[$producerId] ?? null;

        return ! $this->isCancelled()
            && $producerOrder !== null
            && $producerOrder->isParticipating()
            && ! $producerOrder->isSent()
            && ! $this->isOpenFor($producerId, $now);
    }

    /**
     * @param  list<Order>  $orders
     * @param  array<string, array<string, true>>  $marksByOrder
     * @return list<array{productId: string, name: string, unit: string, quantity: Quantity}>
     */
    public function totalsToSend(string $producerId, array $orders, array $marksByOrder): array
    {
        return $this->producerOrderOf($producerId)->totalsToSend($orders, $marksByOrder);
    }

    /**
     * @param  list<Order>  $orders
     * @param  array<string, array<string, true>>  $marksByOrder
     * @return array<string, list<string>> marked product ids per order id
     */
    public function markedLines(string $producerId, array $orders, array $marksByOrder): array
    {
        return $this->producerOrderOf($producerId)->markedLines($orders, $marksByOrder);
    }

    /** @return list<string> producers that are participating, closed and not sent (D10) */
    public function producersReadyToSend(DateTimeImmutable $now): array
    {
        return array_values(array_filter(array_keys($this->producerOrders), fn (string $producerId) => $this->isReadyToSend($producerId, $now)));
    }

    /** What a reception needs: the producer is in the cycle and its order is sent (R14). */
    public function assertSent(string $producerId): void
    {
        if (! $this->producerOrderOf($producerId)->isSent()) {
            throw ProducerOrderNotSent::withId($this->id, $producerId);
        }
    }

    public function isSent(string $producerId): bool
    {
        return ($this->producerOrders[$producerId] ?? null)?->isSent() ?? false;
    }

    /** What changing a reception needs: the order is sent and not distributed, since a distributed reception is read-only (R14, R18). */
    public function assertReceptionEditable(string $producerId): void
    {
        $this->assertSent($producerId);

        if ($this->producerOrderOf($producerId)->isDistributed()) {
            throw ProducerDistributed::withId($this->id, $producerId);
        }
    }

    /** Marks the reception as checked; changing a received total clears it again (R17a). */
    public function validate(string $producerId, DateTimeImmutable $now): void
    {
        $this->assertReceptionEditable($producerId);
        $this->producerOrderOf($producerId)->validate($now);
    }

    public function clearValidation(string $producerId): void
    {
        $this->producerOrderOf($producerId)->clearValidation();
    }

    public function isValidated(string $producerId): bool
    {
        return ($this->producerOrders[$producerId] ?? null)?->isValidated() ?? false;
    }

    public function isDistributed(string $producerId): bool
    {
        return ($this->producerOrders[$producerId] ?? null)?->isDistributed() ?? false;
    }

    /**
     * Distributes a validated, balanced reception, which makes it read-only. The cycle is settled the first time every
     * participating producer is distributed; that is the return value (R18, S13, S47).
     */
    public function markDistributed(string $producerId, bool $balanced, DateTimeImmutable $now): bool
    {
        $this->assertReceptionEditable($producerId);
        $producerOrder = $this->producerOrderOf($producerId);

        if (! $producerOrder->isValidated()) {
            throw ReceptionNotValidated::withId($this->id, $producerId);
        }

        if (! $balanced) {
            throw ReceptionUnbalanced::withId($this->id, $producerId);
        }

        $producerOrder->distribute($now);

        if ($this->isSettled() || ! $this->everyParticipatingProducerIsDistributed()) {
            return false;
        }

        $this->settledAt = $now;

        return true;
    }

    /**
     * Reopens a producer until cycle closure, without stopping settled pickups (R18).
     */
    public function reopen(string $producerId): void
    {
        $this->assertNotClosed();
        $producerOrder = $this->producerOrderOf($producerId);

        if (! $producerOrder->isDistributed()) {
            throw ProducerNotDistributed::withId($this->id, $producerId);
        }

        $producerOrder->reopen();
    }

    public function isFullyDistributed(): bool
    {
        return $this->everyParticipatingProducerIsDistributed();
    }

    public function close(DateTimeImmutable $now, bool $everyPickupDone): void
    {
        if ($this->isClosed() || ! $this->isSettled() || ! $everyPickupDone || ! $this->everyParticipatingProducerIsDistributed()) {
            return;
        }

        $this->closedAt = $now;
    }

    public function isClosed(): bool
    {
        return $this->closedAt !== null;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function assertNotClosed(): void
    {
        if ($this->isClosed()) {
            throw CycleClosed::withId($this->id);
        }
    }

    public function isSettled(): bool
    {
        return $this->settledAt !== null;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    /**
     * Moves the cutoff to a future moment from 6 days before the pickup date at 00:00 up to, not including,
     * the pickup date at 00:00; it works even if the current cutoff has passed (R6).
     */
    public function setCutoff(string $producerId, DateTimeImmutable $at, DateTimeImmutable $now): void
    {
        $this->assertNotCancelled();
        $producerOrder = $this->unsentProducerOrderOf($producerId);

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
        $this->assertNotCancelled();
        $this->unsentProducerOrderOf($producerId)->changeCutoff($now);
    }

    public function clearCutoff(string $producerId): void
    {
        $this->assertNotCancelled();
        $this->unsentProducerOrderOf($producerId)->changeCutoff(null);
    }

    /** Open until its cutoff, or until the pickup date at 00:00 when it has none (R6a). */
    public function isOpenFor(string $producerId, DateTimeImmutable $now): bool
    {
        $producerOrder = $this->producerOrders[$producerId] ?? null;

        if ($this->isCancelled() || $producerOrder === null || ! $producerOrder->isParticipating() || $producerOrder->isSent()) {
            return false;
        }

        return $now < ($producerOrder->cutoff() ?? $this->closesWithoutCutoff($now->getTimezone()));
    }

    public function hasProducer(string $producerId): bool
    {
        return isset($this->producerOrders[$producerId]);
    }

    public function isParticipating(string $producerId): bool
    {
        return ($this->producerOrders[$producerId] ?? null)?->isParticipating() ?? false;
    }

    /** A cycle is open for members while at least one participating producer is open (R4a). */
    public function isOpenForMembers(DateTimeImmutable $now): bool
    {
        foreach ($this->producerOrders as $producerId => $producerOrder) {
            if ($this->isOpenFor($producerId, $now)) {
                return true;
            }
        }

        return false;
    }

    /** What a line change needs: the producer takes part in the cycle and is open (R3, R8). */
    public function assertOpenFor(string $producerId, DateTimeImmutable $now): void
    {
        $this->assertNotCancelled();

        if (! $this->isParticipating($producerId)) {
            throw ProducerNotParticipating::withId($producerId);
        }

        if (! $this->isOpenFor($producerId, $now)) {
            throw ProducerClosed::withId($producerId);
        }
    }

    /** Cancels the cycle; its orders are discarded by the caller. Refused once any producer order is sent (R4b, D14, S37). */
    public function cancel(DateTimeImmutable $now): void
    {
        $this->assertNotCancelled();

        foreach ($this->producerOrders as $producerId => $producerOrder) {
            if ($producerOrder->isSent()) {
                throw ProducerOrderSent::withId($this->id, $producerId);
            }
        }

        $this->cancelledAt = $now;
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
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

    private function everyParticipatingProducerIsDistributed(): bool
    {
        $participating = array_filter($this->producerOrders, fn (ProducerOrder $order) => $order->isParticipating());

        return $participating !== [] && array_filter($participating, fn (ProducerOrder $order) => ! $order->isDistributed()) === [];
    }

    private function assertNotCancelled(): void
    {
        $this->assertNotClosed();

        if ($this->isCancelled()) {
            throw CycleCancelled::withId($this->id);
        }
    }

    private function producerOrderOf(string $producerId): ProducerOrder
    {
        return $this->producerOrders[$producerId] ?? throw ProducerNotInCycle::withId($this->id, $producerId);
    }

    private function unsentProducerOrderOf(string $producerId): ProducerOrder
    {
        $producerOrder = $this->producerOrderOf($producerId);

        if ($producerOrder->isSent()) {
            throw ProducerOrderSent::withId($this->id, $producerId);
        }

        return $producerOrder;
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
