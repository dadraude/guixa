<?php

use Guixa\Membership\Domain\Household;

$when = new DateTimeImmutable('2026-11-01');

it('keeps the first deactivation moment when a household is deactivated again', function () use ($when) {
    $household = Household::create('h', 'Household K');
    $household->deactivate($when);
    $household->deactivate(new DateTimeImmutable('2026-12-01'));

    expect($household->deactivatedAt)->toEqual($when)->and($household->isActive())->toBeFalse();
});
