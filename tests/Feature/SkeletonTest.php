<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('the sign-in page renders in Catalan', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="ca"', escape: false)
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));

    expect(__('Log in'))->toBe('Inicia la sessió');
});

test('the home page redirects to the dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

test('there is no registration', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('there is no password reset', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse()
        ->and(Route::has('password.update'))->toBeFalse();

    $this->get('/forgot-password')->assertNotFound();
});

test('there is no two-factor authentication', function () {
    expect(Route::has('two-factor.login'))->toBeFalse()
        ->and(Route::has('two-factor.enable'))->toBeFalse();

    $this->get('/two-factor-challenge')->assertNotFound();
});

test('there is no self-service account deletion', function () {
    expect(Route::has('profile.destroy'))->toBeFalse();
});

test('users have a UUIDv7 primary key', function () {
    $user = User::factory()->create();

    expect(Schema::getColumnType('users', 'id'))->toBe('uuid')
        ->and(Str::isUuid($user->id, 7))->toBeTrue();
});

test('sessions last weeks', function () {
    expect(config('session.lifetime'))->toBeGreaterThanOrEqual(60 * 24 * 7);
});

test('the time zone is Europe/Madrid', function () {
    expect(config('app.timezone'))->toBe('Europe/Madrid');
});

test('sign-in errors are in Catalan', function () {
    $this->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'El camp adreça electrònica és obligatori.']);
});

test('every user-facing string has a Catalan translation', function () {
    $catalan = json_decode(file_get_contents(lang_path('ca.json')), true);

    $files = collect(File::allFiles(resource_path('js')))
        ->merge(File::allFiles(app_path()))
        ->reject(fn ($file) => Str::contains($file->getPathname(), ['/routes/', '/actions/', '/wayfinder/']));

    $missing = $files
        ->flatMap(function ($file) {
            preg_match_all("/(?<![\w.])(?:t|__)\(\s*(?:'((?:[^'\\\\]|\\\\.)+)'|\"((?:[^\"\\\\]|\\\\.)+)\")/", $file->getContents(), $matches);

            return array_filter(array_merge($matches[1], $matches[2]), fn ($key) => $key !== '');
        })
        ->map(fn (string $key) => stripslashes($key))
        ->unique()
        ->reject(fn (string $key) => array_key_exists($key, $catalan))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('the stubs generate UUID primary keys', function () {
    expect(file_get_contents(base_path('stubs/model.stub')))
        ->toContain('use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;')
        ->toContain('use HasUuids;')
        ->and(file_get_contents(base_path('stubs/migration.create.stub')))
        ->toContain("\$table->uuid('id')->primary();");
});
