<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has not set a password yet.
     */
    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
        ]);
    }

    /**
     * Give the user an administrator profile.
     */
    public function administrator(bool $active = true): static
    {
        return $this->afterCreating(function (User $user) use ($active) {
            $user->administrator()->create(['is_active' => $active]);
        });
    }

    /**
     * Give the user a member profile in the given household.
     */
    public function member(Household $household): static
    {
        return $this->afterCreating(function (User $user) use ($household) {
            $user->member()->create(['household_id' => $household->id]);
        });
    }

    /**
     * Make the user the owner: the administrator created from the command line.
     */
    public function owner(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->administrator()->create(['is_owner' => true]);
        });
    }
}
