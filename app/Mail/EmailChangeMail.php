<?php

namespace App\Mail;

use App\Actions\RequestEmailChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailChangeMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Confirm your new email address for :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.email-change',
            with: ['hours' => RequestEmailChange::HOURS_VALID],
        );
    }
}
