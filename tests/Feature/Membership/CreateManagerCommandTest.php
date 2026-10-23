<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// R36a, D17 — guixa:create-manager

it('creates the first manager and emails the invitation', function () {
    Mail::fake();

    $this->artisan('guixa:create-manager', ['name' => 'Marta', 'email' => 'marta@example.org'])
        ->expectsOutput('Invitation sent to marta@example.org.')
        ->assertSuccessful();

    $id = DB::table('users')->where('email', 'marta@example.org')->value('id');
    expect(DB::table('account_roles')->where('user_id', $id)->pluck('role')->all())->toBe(['manager']);
    Mail::assertSentCount(1);
});

it('fails once any manager exists', function () {
    Mail::fake();
    $this->artisan('guixa:create-manager', ['name' => 'Marta', 'email' => 'marta@example.org'])->assertSuccessful();

    $this->artisan('guixa:create-manager', ['name' => 'Biel', 'email' => 'biel@example.org'])
        ->expectsOutput('A manager already exists; the first manager can only be created once.')
        ->assertFailed();

    expect(DB::table('users')->where('email', 'biel@example.org')->exists())->toBeFalse();
    Mail::assertSentCount(1);
});
