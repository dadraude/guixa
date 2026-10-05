<?php

declare(strict_types=1);

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

test('the command creates the owner and emails them a magic link', function () {
    $this->artisan('guixa:create-owner', ['email' => 'Anna@Example.com', 'name' => 'Anna Puig'])
        ->expectsOutputToContain('anna@example.com')
        ->assertSuccessful();

    $owner = User::where('email', 'anna@example.com')->sole();

    expect($owner->name)->toBe('Anna Puig')
        ->and($owner->password)->toBeNull()
        ->and($owner->administrator->is_owner)->toBeTrue()
        ->and($owner->administrator->is_active)->toBeTrue();

    Mail::assertQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('anna@example.com'));
});

test('the command refuses to run when an owner already exists', function () {
    User::factory()->owner()->create();

    $this->artisan('guixa:create-owner', ['email' => 'other@example.com', 'name' => 'Other'])
        ->expectsOutputToContain(__('An owner already exists.'))
        ->assertFailed();

    expect(User::count())->toBe(1);
    Mail::assertNothingQueued();
});

test('the command rejects an invalid email', function () {
    $this->artisan('guixa:create-owner', ['email' => 'not-an-email', 'name' => 'Anna'])
        ->assertFailed();

    expect(User::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('the command rejects an email that is already in use', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->artisan('guixa:create-owner', ['email' => 'anna@example.com', 'name' => 'Anna'])
        ->assertFailed();

    expect(User::count())->toBe(1);
    Mail::assertNothingQueued();
});

test('the magic-link email is in Catalan', function () {
    $this->artisan('guixa:create-owner', ['email' => 'anna@example.com', 'name' => 'Anna Puig'])
        ->assertSuccessful();

    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) {
        $mail->assertHasSubject('Enllaç per iniciar la sessió a '.config('app.name'));
        $mail->assertSeeInHtml('Inicia la sessió');
        $mail->assertSeeInHtml('15 minuts');

        return true;
    });
});
