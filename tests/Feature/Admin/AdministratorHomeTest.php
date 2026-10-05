<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an administrator lands on the administrator home', function () {
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertRedirect(route('admin.home'));
});

test('the layout shows that the person is acting as administrator', function () {
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Home')
            ->where('auth.actingAs', 'administrator'));

    expect(__('Acting as administrator'))->toBe('Actues com a administrador/a');
});

test('an active administrator who is not the owner reaches the administrator home', function () {
    $this->actingAs(User::factory()->administrator()->create())
        ->get(route('admin.home'))
        ->assertOk();
});

test('an inactive administrator is signed out instead of reaching the administrator home', function () {
    $admin = User::factory()->administrator(active: false)->create();

    $this->actingAs($admin)->get(route('admin.home'))->assertRedirect(route('login'));
});

test('a user without an administrator profile cannot reach the administrator home', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.home'))
        ->assertForbidden();
});

test('a user without an administrator profile lands on the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
