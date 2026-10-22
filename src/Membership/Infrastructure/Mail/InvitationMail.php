<?php

namespace Guixa\Membership\Infrastructure\Mail;

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
        return new Envelope(subject: 'Set your Guixa password');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation');
    }
}
