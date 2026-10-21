<?php

use Guixa\Shared\Domain\Clock;

it('reads the cooperative timezone from COOP_TIMEZONE through the container', function () {
    config(['guixa.timezone' => 'Atlantic/Canary']);
    app()->forgetInstance(Clock::class);

    expect(app(Clock::class)->timezone()->getName())->toBe('Atlantic/Canary');
});
