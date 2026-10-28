<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginResponseTest extends TestCase
{
    public static function loginResponses(): array
    {
        return [
            'password' => [LoginResponse::class, ['two_factor' => false], 200],
            'two factor' => [TwoFactorLoginResponse::class, null, 204],
            'passkey' => [PasskeyLoginResponse::class, ['redirect' => '/dashboard'], 200],
        ];
    }

    #[DataProvider('loginResponses')]
    public function test_successful_login_response_targets_dashboard(string $contract, ?array $json, int $status): void
    {
        Route::middleware('web')->get('/probe/login-response', fn () => app($contract));

        $this->get('/probe/login-response')->assertRedirect(route('dashboard', absolute: false));

        $this->withSession(['url.intended' => route('home')])
            ->get('/probe/login-response')
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionMissing('url.intended');
    }

    #[DataProvider('loginResponses')]
    public function test_login_json_contract_is_preserved(string $contract, ?array $json, int $status): void
    {
        Route::middleware('web')->get('/probe/login-response', fn () => app($contract));

        if ($contract === PasskeyLoginResponse::class) {
            $json = ['redirect' => route('dashboard')];
        }

        $response = $this->withSession(['url.intended' => route('home')])
            ->getJson('/probe/login-response')
            ->assertStatus($status)
            ->assertSessionMissing('url.intended');

        if ($json !== null) {
            $response->assertExactJson($json);
        }
    }
}
