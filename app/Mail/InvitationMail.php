<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly string $householdName,
        public readonly ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invitation to join :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation',
            with: ['days' => Invitation::DAYS_VALID],
        );
    }
}
