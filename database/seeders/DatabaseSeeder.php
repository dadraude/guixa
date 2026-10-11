<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->owner()->create([
            'name' => 'Owner User',
            'email' => 'owner@draude.cat',
            'password' => 'password',
        ]);

        // Every user but the owner has a member profile.
        User::factory()->memberWithoutHousehold()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
