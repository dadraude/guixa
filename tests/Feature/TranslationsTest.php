<?php

use App\Models\User;
use Guixa\Membership\Infrastructure\Mail\InvitationMail;
use Guixa\Ordering\Infrastructure\Membership\ProducerNotifierMail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(fn () => app()->setLocale('ca'));

it('renders the invitation subject and body in Catalan with a localized expiry', function () {
    $mail = new InvitationMail('Anna', 'https://example.test/accept', new DateTimeImmutable('2026-11-05 17:30:00', new DateTimeZone('Europe/Madrid')));

    expect($mail->envelope()->subject)->toBe('Estableix la contrasenya de Guixa')
        ->and($mail->render())->toContain('Hola Anna', 'Estableix la contrasenya', '5 de novembre de 2026, 17:30')
        ->not->toContain('Set your password', 'The link works until');
});

it('renders the producer order subject and body in Catalan while escaping product data', function () {
    $mail = new ProducerNotifierMail(new DateTimeImmutable('2026-11-05'), [
        ['name' => '<Tomàquet>', 'unit' => 'kg', 'quantity' => '12345.678'],
    ]);

    expect($mail->envelope()->subject)->toBe('Comanda de Guixa per al 05/11/2026')
        ->and($mail->render())->toContain('Comanda per a la recollida del 05/11/2026', '&lt;Tomàquet&gt;', '12.345,678 kg')
        ->not->toContain('Order for the pickup', '<Tomàquet>');
});

it('renders framework reset and verification notification copy in Catalan', function () {
    $user = User::factory()->make(['email' => 'anna@example.test']);
    ResetPassword::createUrlUsing(fn () => 'https://example.test/reset');
    VerifyEmail::createUrlUsing(fn () => 'https://example.test/verify');

    $reset = (new ResetPassword('token'))->toMail($user);
    $verify = (new VerifyEmail)->toMail($user);

    expect($reset->subject)->toBe('Restableix la contrasenya')
        ->and($reset->actionText)->toBe('Restableix la contrasenya')
        ->and(implode(' ', $reset->introLines))->toContain('sol·licitud per restablir la contrasenya')
        ->and($verify->subject)->toBe('Verifica la teva adreça electrònica')
        ->and($verify->actionText)->toBe('Verifica l’adreça electrònica')
        ->and(implode(' ', $verify->introLines))->toContain('verificar la teva adreça electrònica')
        ->and($reset->render()->toHtml())->toContain('Hola!', 'Si tens problemes per prémer el botó', 'Salutacions,')
        ->and($verify->render()->toHtml())->toContain('Hola!', 'Verifica l’adreça electrònica', 'Tots els drets reservats.');
});

it('uses Catalan framework authentication, password, pagination and validation messages', function () {
    $validator = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
    $pickupValidator = Validator::make(['pickup_weekday' => null], ['pickup_weekday' => 'required']);
    $cutoffValidator = Validator::make(['days_before' => null], ['days_before' => 'required']);
    $recoveryValidator = Validator::make(['recovery_code' => null], ['recovery_code' => 'required']);
    $cycleValidator = Validator::make(['cycle_id' => 'invalid'], ['cycle_id' => 'uuid']);

    expect(__('auth.failed'))->toBe('Aquestes credencials no concorden amb els nostres registres.')
        ->and(__('passwords.reset'))->toBe("La contrasenya s'ha restablert!")
        ->and(__('pagination.next'))->toBe('Següent &raquo;')
        ->and($validator->errors()->first('email'))->toContain('correu electrònic')
        ->and($pickupValidator->errors()->first('pickup_weekday'))->toContain('dia de recollida')
        ->and($cutoffValidator->errors()->first('days_before'))->toContain('dies abans de la recollida')
        ->and($recoveryValidator->errors()->first('recovery_code'))->toContain('codi de recuperació')
        ->and($cycleValidator->errors()->first('cycle_id'))->toContain('cicle');
});

it('translates each passkey validation error emitted by the installed package', function (string $source, string $translated) {
    expect(InvalidPasskeyException::make($source)->errors()['credential'][0])->toBe($translated);
})->with([
    ['Unable to sign in with this account.', 'No s’ha pogut iniciar sessió amb aquest compte.'],
    ['Unable to register this passkey.', 'No s’ha pogut registrar aquesta clau d’accés.'],
    ['Unable to verify passkey. Please try again.', 'No s’ha pogut verificar la clau d’accés. Torna-ho a provar.'],
    ['Passkey not recognized. It may have been removed from your account.', 'No s’ha reconegut la clau d’accés. Potser s’ha eliminat del teu compte.'],
]);

it('returns a translated domain denial with the existing HTTP 403 contract', function () {
    Route::middleware('web')->get('/probe/translated-denial', fn () => throw new AuthorizationException('Internal role detail'));

    $this->getJson('/probe/translated-denial')
        ->assertForbidden()
        ->assertJsonPath('message', 'No tens permís per fer aquesta acció.');
});

it('renders the default framework forbidden page and JSON denial in Catalan', function () {
    Route::middleware('web')->get('/probe/default-forbidden', fn () => abort(403));

    $this->get('/probe/default-forbidden')->assertForbidden()->assertSee('Prohibit')->assertSee('<html lang="ca">', false);
    $this->getJson('/probe/default-forbidden')->assertForbidden()->assertJsonPath('message', 'Prohibit');
});

it('renders each framework error page with a translated title and document language', function (int $status, string $title) {
    config()->set('app.debug', false);
    Route::middleware('web')->get('/probe/error-page-'.$status, fn () => abort($status));

    $this->get('/probe/error-page-'.$status)
        ->assertStatus($status)
        ->assertSee('<html lang="ca">', false)
        ->assertSee('<title>'.$title.'</title>', false)
        ->assertSee($title);
})->with([
    [401, 'No autoritzat'],
    [402, 'Pagament requerit'],
    [403, 'Prohibit'],
    [404, 'No s’ha trobat la pàgina'],
    [419, 'La pàgina ha caducat'],
    [429, 'Massa sol·licituds'],
    [500, 'Error del servidor'],
    [503, 'Servei no disponible'],
]);

it('uses translated JSON error titles for default framework responses', function (int $status, string $title) {
    config()->set('app.debug', false);
    Route::middleware('web')->get('/probe/error-json-'.$status, fn () => abort($status));

    $this->getJson('/probe/error-json-'.$status)
        ->assertStatus($status)
        ->assertJsonPath('message', $title);
})->with([
    [401, 'No autoritzat'],
    [402, 'Pagament requerit'],
    [403, 'Prohibit'],
    [404, 'No s’ha trobat la pàgina'],
    [419, 'La pàgina ha caducat'],
    [429, 'Massa sol·licituds'],
    [500, 'Error del servidor'],
    [503, 'Servei no disponible'],
]);

it('preserves HTTP headers when translating default JSON error titles', function (int $status, string $header, string $value, string $title) {
    Route::middleware('web')->get('/probe/error-header-'.$status, fn () => throw new HttpException($status, '', null, [$header => $value]));

    $this->getJson('/probe/error-header-'.$status)
        ->assertStatus($status)
        ->assertHeader($header, $value)
        ->assertJsonPath('message', $title);
})->with([
    [429, 'Retry-After', '60', 'Massa sol·licituds'],
    [401, 'WWW-Authenticate', 'Bearer realm="guixa"', 'No autoritzat'],
    [503, 'Retry-After', '120', 'Servei no disponible'],
]);

it('translates a real throttled JSON response while retaining its rate limit headers', function () {
    config()->set('cache.default', 'array');
    Route::middleware(['web', 'throttle:1,1'])->get('/probe/translated-throttle', fn () => response()->json(['ok' => true]));
    $kernel = app(Kernel::class);
    $request = fn () => Request::create('/probe/translated-throttle', 'GET', server: ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.205']);

    $first = $kernel->handle($request());
    $second = $kernel->handle($request());

    expect($first->getStatusCode())->toBe(200)
        ->and($second->getStatusCode())->toBe(429)
        ->and($second->headers->get('Retry-After'))->not->toBeNull()
        ->and(json_decode($second->getContent(), true)['message'])->toBe('Massa sol·licituds');
});

it('translates a real CSRF middleware JSON response', function () {
    Route::middleware('web')->post('/probe/translated-csrf', fn () => response()->json(['ok' => true]));
    $request = Request::create('/probe/translated-csrf', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $originalEnvironment = app('env');
    app()->instance('env', 'production'); // Laravel bypasses CSRF only while its application environment is testing.
    try {
        $response = app(Kernel::class)->handle($request);
    } finally {
        app()->instance('env', $originalEnvironment);
    }

    expect($response->getStatusCode())->toBe(419)
        ->and(json_decode($response->getContent(), true)['message'])->toBe('La pàgina ha caducat');
});

it('translates framework authentication and model-missing JSON without exposing technical model details', function () {
    config()->set('app.debug', false);
    Route::middleware('web')->get('/probe/authentication-exception', fn () => throw new AuthenticationException);
    Route::middleware('web')->get('/probe/model-missing', fn () => throw (new ModelNotFoundException)->setModel(User::class, ['internal-id']));

    $this->getJson('/probe/authentication-exception')->assertUnauthorized()->assertJsonPath('message', 'No autenticat.');
    $this->getJson('/probe/model-missing')->assertNotFound()->assertJsonPath('message', 'No s’ha trobat la pàgina');
});

it('retains custom HTTP exception messages and headers verbatim', function () {
    Route::middleware('web')->get('/probe/custom-http-message', fn () => throw new HttpException(429, 'Custom quota detail', null, ['Retry-After' => '15']));

    $this->getJson('/probe/custom-http-message')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '15')
        ->assertJsonPath('message', 'Custom quota detail');
});

it('returns the generic translated server error without exposing an unexpected exception', function () {
    config()->set('app.debug', false);
    config()->set('logging.default', 'null');
    Route::middleware('web')->get('/probe/unexpected-error', fn () => throw new RuntimeException('Internal technical detail'));

    $this->getJson('/probe/unexpected-error')->assertStatus(500)->assertJsonPath('message', 'Error del servidor');
});

it('uses localized field names and summary counts in actual JSON validation responses', function () {
    Route::middleware('web')->post('/probe/validation-json', function () {
        Validator::make(request()->all(), [
            'email' => ['required', 'email'],
            'cycle_id' => ['required', 'uuid'],
        ])->validate();
    });

    $response = $this->postJson('/probe/validation-json', []);
    $response->assertUnprocessable()->assertJsonPath('errors.cycle_id.0', 'El camp cicle és obligatori.');
    expect($response->json('message'))->toContain('correu electrònic', '1 error més')
        ->not->toContain('more error');
});

it('localizes every active nested passkey validation field in JSON', function (string $requestClass) {
    Route::middleware('web')->post('/probe/passkey-fields', function () use ($requestClass) {
        Validator::make(request()->all(), (new $requestClass)->rules())->validate();
    });

    $missing = $this->postJson('/probe/passkey-fields', [
        'name' => 'MacBook',
        'credential' => ['id' => 'id', 'type' => 'public-key'],
    ]);
    $missing->assertUnprocessable();
    expect($missing->json('errors')['credential.rawId'][0])->toBe('El camp identificador de la clau d’accés és obligatori.')
        ->and($missing->json('errors')['credential.response'][0])->toBe('El camp resposta de la clau d’accés és obligatori.');
    expect($missing->json('message'))->toContain('identificador de la clau d’accés', '1 error més');

    $invalid = $this->postJson('/probe/passkey-fields', [
        'name' => str_repeat('x', 256),
        'credential' => ['rawId' => 'raw', 'type' => 'invalid', 'response' => []],
        'remember' => 'invalid',
    ]);
    $invalid->assertUnprocessable();
    expect($invalid->json('errors')['credential.id'][0])->toContain('identificador de la credencial')
        ->and($invalid->json('errors')['credential.type'][0])->toContain('Tipus de credencial');
    if ($requestClass === PasskeyRegistrationRequest::class) {
        expect($invalid->json('errors.name.0'))->toContain('Nom');
    }
    if ($requestClass === PasskeyVerificationRequest::class) {
        expect($invalid->json('errors')['remember'][0])->toContain('recorda la sessió');
    }
})->with([PasskeyRegistrationRequest::class, PasskeyVerificationRequest::class]);

it('keeps the client passkey validation display templates aligned with Laravel', function () {
    $catalog = json_decode(file_get_contents(base_path('lang/ca.json')), true, flags: JSON_THROW_ON_ERROR);
    $attributes = __('validation.attributes');
    foreach ([PasskeyRegistrationRequest::class, PasskeyVerificationRequest::class] as $requestClass) {
        foreach (array_keys((new $requestClass)->rules()) as $field) {
            expect($attributes)->toHaveKey($field);
        }
    }
    foreach ([
        'name' => 'name',
        'passkey credential' => 'credential',
        'credential ID' => 'credential.id',
        'raw passkey ID' => 'credential.rawId',
        'credential type' => 'credential.type',
        'passkey response' => 'credential.response',
        'remember session' => 'remember',
    ] as $source => $field) {
        expect($catalog[$source])->toBe($attributes[$field]);
    }
    foreach ([
        'The :attribute field is required.' => 'validation.required',
        'The :attribute field must be a string.' => 'validation.string',
        'The :attribute field must be an array.' => 'validation.array',
        'The selected :attribute is invalid.' => 'validation.in',
        'The :attribute field must be true or false.' => 'validation.boolean',
        'The :attribute field must not be greater than :max characters.' => 'validation.max.string',
    ] as $source => $rule) {
        expect($catalog[$source])->toBe(__($rule));
    }
});
