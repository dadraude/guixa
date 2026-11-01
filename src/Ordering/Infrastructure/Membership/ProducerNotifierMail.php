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
        return new Envelope(subject: __('Guixa order for :date', ['date' => $this->pickupDate->format('d/m/Y')]));
    }

    public function content(): Content
    {
        $formattedTotals = array_map(
            fn (array $total) => [
                'name' => $total['name'],
                'quantity' => self::formatQuantity($total['quantity']),
                'unit' => $total['unit'],
            ],
            $this->totals,
        );

        return new Content(view: 'mail.producer-order', with: [
            'heading' => __('Order for the pickup on :date:', ['date' => $this->pickupDate->format('d/m/Y')]),
            'formattedTotals' => $formattedTotals,
        ]);
    }

    private static function formatQuantity(string $quantity): string
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $quantity, $parts)) {
            return $quantity;
        }

        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[2]);

        return ($parts[1] === '-' ? '−' : '').$integer.(isset($parts[3]) ? ','.$parts[3] : '');
    }
}
