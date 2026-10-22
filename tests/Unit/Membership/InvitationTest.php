<?php

use Guixa\Membership\Domain\Invitation;

it('expires 168 elapsed hours after it was sent, across a daylight-saving change', function () {
    $sentAt = new DateTimeImmutable('2026-10-22 10:00', new DateTimeZone('Europe/Madrid'));

    $invitation = Invitation::issue('account', 'token', $sentAt);

    expect($invitation->expiresAt()->getTimestamp() - $sentAt->getTimestamp())->toBe(7 * 24 * 3600);
});

it('has the same expiry when issued in the cooperative timezone and when loaded with a fixed offset', function () {
    $fresh = Invitation::issue('account', 'token', new DateTimeImmutable('2026-10-22 10:00', new DateTimeZone('Europe/Madrid')));
    $loaded = Invitation::restore('account', $fresh->tokenHash, new DateTimeImmutable('2026-10-22 08:00:00+00'));

    expect($loaded->expiresAt()->getTimestamp())->toBe($fresh->expiresAt()->getTimestamp())
        ->and($fresh->expiresAt()->format(DATE_ATOM))->toBe('2026-10-29T09:00:00+01:00');
});
