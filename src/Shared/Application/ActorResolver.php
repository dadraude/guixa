<?php

namespace Guixa\Shared\Application;

interface ActorResolver
{
    public function forAccount(string $accountId): Actor;
}
