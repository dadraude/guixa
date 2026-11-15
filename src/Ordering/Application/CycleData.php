<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\ProducerOrder;

final readonly class CycleData
{
    /** @param  list<ParticipantData>  $participants */
    public function __construct(
        public string $id,
        public string $pickupDate,
        public bool $cancelled,
        public array $participants,
        public bool $closed = false,
        public bool $settled = false,
    ) {}

    /** @param  list<string>  $manuallySendable  producers whose order a manager can send now */
    public static function of(Cycle $cycle, DateTimeImmutable $now, DateTimeZone $timezone, array $manuallySendable = []): self
    {
        return new self(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            $cycle->isCancelled(),
            array_map(
                fn (ProducerOrder $order) => new ParticipantData(
                    $order->producerId,
                    $order->isParticipating(),
                    $order->cutoff()?->setTimezone($timezone)->format('c'),
                    $cycle->isOpenFor($order->producerId, $now),
                    $order->isSent(),
                    in_array($order->producerId, $manuallySendable, true),
                ),
                $cycle->producerOrders(),
            ),
            $cycle->isClosed(),
            $cycle->isSettled(),
        );
    }
}
