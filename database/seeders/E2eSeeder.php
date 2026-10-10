<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data for the Playwright tests: one member created by hand in a household, so
 * the sign-in test starts where a new member does, from the welcome email, and
 * an owner (password "password", from the UserFactory) plus two households for
 * the household status dialog test.
 */
class E2eSeeder extends Seeder
{
    public const string MEMBER_EMAIL = 'e2e-member@example.test';

    public const string OWNER_EMAIL = 'e2e-owner@example.test';

    public function run(CreateMember $createMember): void
    {
        $createMember->handle(
            'Anna E2E',
            self::MEMBER_EMAIL,
            null,
            Household::create(['name' => 'Cal E2E'])->id,
            [],
        );

        User::factory()->owner()->create(['name' => 'Propietària E2E', 'email' => self::OWNER_EMAIL]);

        $withMembers = Household::create(['name' => 'Cal Gran E2E']);
        User::factory()->member($withMembers)->create(['name' => 'Berta E2E']);
        User::factory()->member($withMembers)->create(['name' => 'Carles E2E']);

        Household::create(['name' => 'Cal Buit E2E']);
    }
}
