<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('an administrator without a password is sent to set one from any page', function (string $route) {
    $admin = User::factory()->owner()->withoutPassword()->create();

    $this->actingAs($admin)
        ->get(route($route))
        ->assertRedirect(route('password.setup'));
})->with(['home', 'dashboard', 'admin.home', 'profile.edit']);

test('the set-password screen renders in Catalan', function () {
    $admin = User::factory()->owner()->withoutPassword()->create();

    $this->actingAs($admin)
        ->get(route('password.setup'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/SetPassword'));

    expect(__('Set your password'))->toBe('Tria la teva contrasenya');
});

test('setting the password takes the administrator to the administrator home', function () {
    $admin = User::factory()->owner()->withoutPassword()->create();

    $this->actingAs($admin)
        ->post(route('password.setup.store'), [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])
        ->assertRedirect(route('home'));

    expect(Hash::check('a-new-password', $admin->fresh()->password))->toBeTrue();

    $this->get(route('home'))->assertRedirect(route('admin.home'));
    $this->get(route('admin.home'))->assertOk();
});

test('the password must be confirmed', function () {
    $admin = User::factory()->owner()->withoutPassword()->create();

    $this->actingAs($admin)
        ->post(route('password.setup.store'), [
            'password' => 'a-new-password',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password');

    expect($admin->fresh()->password)->toBeNull();
});

test('a user who already has a password cannot use the set-password screen', function () {
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)
        ->get(route('password.setup'))
        ->assertRedirect(route('home'));
});

test('signing out works without a password', function () {
    $admin = User::factory()->owner()->withoutPassword()->create();

    $this->actingAs($admin)->post(route('logout'));

    $this->assertGuest();
});
