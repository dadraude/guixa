<?php

use Guixa\Membership\Infrastructure\Mail\InvitationMail;
use Tests\TestCase;

uses(TestCase::class);

it('shows the expiry in the cooperative timezone when the stored instant is UTC', function () {
    config(['guixa.timezone' => 'Europe/Madrid']);
    $mail = new InvitationMail('Anna', 'https://example.test/accept', new DateTimeImmutable('2026-10-28 23:30:00', new DateTimeZone('UTC')));

    expect($mail->localizedExpiry())->toBe('29 de octubre de 2026, 00:30');
});
