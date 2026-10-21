<?php

namespace Guixa\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;

interface Clock
{
    public function now(): DateTimeImmutable;

    /** The cooperative's timezone: every local date and time in the specs is read in it. */
    public function timezone(): DateTimeZone;
}
