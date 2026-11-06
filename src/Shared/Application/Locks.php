<?php

namespace Guixa\Shared\Application;

interface Locks
{
    public const MEMBERSHIP_CHANGES = 'membership-changes';

    /** Blocks until the named lock is free, then holds it until the current transaction ends. */
    public function acquire(string $name): void;
}
