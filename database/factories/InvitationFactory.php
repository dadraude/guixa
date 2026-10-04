<?php

namespace Database\Factories;

use App\Auth\SecretHasher;
use App\Models\Household;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => SecretHasher::hash(Str::random(64)),
            'expires_at' => now()->addDays(Invitation::DAYS_VALID),
        ];
    }
}
