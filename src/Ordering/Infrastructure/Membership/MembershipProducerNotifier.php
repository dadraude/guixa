<?php

namespace Guixa\Ordering\Infrastructure\Membership;

use DateTimeImmutable;
use Guixa\Membership\Application\GetProducerAccountEmail;
use Guixa\Ordering\Domain\ProducerNotifier;
use Illuminate\Support\Facades\Mail;

/** Emails the producer's account (Membership) the totals of a sent order (R12a, A4). */
final class MembershipProducerNotifier implements ProducerNotifier
{
    public function __construct(private readonly GetProducerAccountEmail $accountEmail) {}

    public function canNotify(string $producerId): bool
    {
        return ($this->accountEmail)($producerId) !== null;
    }

    public function notify(string $producerId, DateTimeImmutable $pickupDate, array $totals): void
    {
        $email = ($this->accountEmail)($producerId);

        if ($email === null) {
            return;
        }

        Mail::to($email)->send(new ProducerNotifierMail(
            $pickupDate,
            array_map(fn (array $total) => ['name' => $total['name'], 'unit' => $total['unit'], 'quantity' => $total['quantity']->toString()], $totals),
        ));
    }
}
