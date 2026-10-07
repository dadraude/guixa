<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateMember;
use App\Models\Household;
use Illuminate\Database\Seeder;

/**
 * Data for the Playwright sign-in test: one member created by hand in a
 * household, so the test starts where a new member does, from the welcome email.
 */
class E2eSeeder extends Seeder
{
    public const string MEMBER_EMAIL = 'e2e-member@example.test';

    public function run(CreateMember $createMember): void
    {
        $createMember->handle(
            'Anna E2E',
            self::MEMBER_EMAIL,
            null,
            Household::create(['name' => 'Cal E2E'])->id,
            [],
        );
    }
}
