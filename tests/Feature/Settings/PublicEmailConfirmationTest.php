<?php

declare(strict_types=1);

use App\Mail\EmailChangeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('email confirmation always ends on the same public success page', function (string $browser) {
    Mail::fake();
    $owner = User::factory()->create(['email' => 'old@example.com']);
    $this->actingAs($owner)->patch(route('profile.update'), ['name' => $owner->name, 'email' => 'new@example.com']);
    $url = Mail::queued(EmailChangeMail::class)->last()->url;

    auth()->logout();
    $signedIn = match ($browser) {
        'owner' => $owner,
        'other' => User::factory()->withoutPassword()->create(),
        default => null,
    };
    if ($signedIn !== null) {
        $this->actingAs($signedIn);
    }

    $this->post($url)->assertRedirect(route('email-change.updated'));
    $this->get(route('email-change.updated'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/EmailChangeUpdated'));
    expect($owner->fresh()->email)->toBe('new@example.com');
    if ($signedIn === null) {
        $this->assertGuest();
    } else {
        $this->assertAuthenticatedAs($signedIn);
        expect(auth()->user()->email)->toBe($browser === 'owner' ? 'new@example.com' : $signedIn->email);
    }
})->with(['guest', 'owner', 'other']);
