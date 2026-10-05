<?php

declare(strict_types=1);

use App\Models\User;

test('guests are redirected to the sign-in page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('signed-in users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});
