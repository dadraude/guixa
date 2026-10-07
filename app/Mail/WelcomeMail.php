<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a newly created member they can sign in. It carries no token: the
 * link only leads to the sign-in screen, where they ask for a magic link.
 */
class WelcomeMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly string $name,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Welcome to :app', ['app' => config()->string('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.welcome');
    }
}
