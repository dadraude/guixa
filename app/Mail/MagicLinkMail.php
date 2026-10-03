<?php

namespace App\Mail;

use App\Auth\MagicLinkBroker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Link to log in to :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.magic-link',
            with: ['minutes' => MagicLinkBroker::MINUTES_VALID],
        );
    }
}
