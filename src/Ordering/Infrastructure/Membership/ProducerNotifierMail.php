<?php

namespace Guixa\Ordering\Infrastructure\Membership;

use DateTimeImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The order a producer has to deliver: the total per product and nothing about households (R12a). */
final class ProducerNotifierMail extends Mailable
{
    /** @param  list<array{name: string, unit: string, quantity: string}>  $totals */
    public function __construct(
        public readonly DateTimeImmutable $pickupDate,
        public readonly array $totals,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Guixa order for '.$this->pickupDate->format('Y-m-d'));
    }

    public function content(): Content
    {
        $lines = array_map(
            fn (array $total) => '- '.e($total['name']).': '.e($total['quantity']).' '.e($total['unit']),
            $this->totals,
        );

        return new Content(htmlString: '<p>Order for the pickup on '.$this->pickupDate->format('Y-m-d').':</p><p>'.implode('<br>', $lines).'</p>');
    }
}
