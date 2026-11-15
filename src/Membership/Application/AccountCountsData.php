<?php

namespace Guixa\Membership\Application;

final readonly class AccountCountsData
{
    public function __construct(public int $total, public int $pendingInvitations) {}
}
