<?php

namespace Guixa\Membership\Infrastructure\Mail;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class InvitationMail extends Mailable
{
    public function __construct(
        public readonly string $name,
        public readonly string $acceptUrl,
        public readonly DateTimeImmutable $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Set your Guixa password'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation', with: ['expiry' => $this->localizedExpiry()]);
    }

    public function localizedExpiry(): string
    {
        $expiry = CarbonImmutable::instance($this->expiresAt)->setTimezone(config('guixa.timezone'));
        $expiry->locale('ca');

        return $expiry->isoFormat('D [de] MMMM [de] YYYY, HH:mm');
    }
}
