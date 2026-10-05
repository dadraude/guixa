<?php

namespace Database\Seeders;

use App\Actions\InviteToHousehold;
use App\Models\Household;
use Illuminate\Database\Seeder;

/**
 * Data for the Playwright sign-in test: one household with a pending
 * invitation, so the test starts where a new member does, from the email.
 */
class E2eSeeder extends Seeder
{
    public const string INVITED_EMAIL = 'e2e-member@example.test';

    public function run(InviteToHousehold $inviteToHousehold): void
    {
        $inviteToHousehold->handle(
            Household::create(['name' => 'Cal E2E']),
            self::INVITED_EMAIL,
            'Anna E2E',
        );
    }
}
