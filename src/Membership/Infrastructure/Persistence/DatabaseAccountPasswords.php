<?php

namespace Guixa\Membership\Infrastructure\Persistence;

use Guixa\Membership\Domain\AccountPasswords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class DatabaseAccountPasswords implements AccountPasswords
{
    public function change(string $accountId, string $plainPassword): void
    {
        DB::table('users')->where('id', $accountId)->update(['password' => Hash::make($plainPassword)]);
    }
}
