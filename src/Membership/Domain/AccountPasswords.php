<?php

namespace Guixa\Membership\Domain;

interface AccountPasswords
{
    public function change(string $accountId, string $plainPassword): void;
}
