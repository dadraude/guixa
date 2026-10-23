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
        public array $participants,
    ) {}

    public static function of(Cycle $cycle, DateTimeImmutable $now, DateTimeZone $timezone): self
    {
        return new self(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            array_map(
                fn (ProducerOrder $order) => new ParticipantData(
                    $order->producerId,
                    $order->isParticipating(),
                    $order->cutoff()?->setTimezone($timezone)->format('c'),
                    $cycle->isOpenFor($order->producerId, $now),
                ),
                $cycle->producerOrders(),
            ),
        );
    }
}
