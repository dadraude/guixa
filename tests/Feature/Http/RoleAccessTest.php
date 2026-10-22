<?php

use App\Models\User;
use Guixa\Probe\Domain\Error\ProbeFailed;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../Support/Fixtures/ProbeFailed.php';

function userWithRoles(Role ...$roles): User
{
    $user = User::factory()->create();

    foreach ($roles as $role) {
        if ($role === Role::Member) {
            linkToNewHousehold($user);

            continue;
        }

        DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => $role->value]);
    }

    return $user;
}

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:manager'])->get('/probe/manage', fn () => 'manager area');
    Route::middleware(['web', 'auth', 'role:producer'])->get('/probe/producer', fn () => 'producer area');
    Route::middleware(['web', 'auth', 'role:member'])->get('/probe/member', fn () => 'member area');
    Route::middleware(['web', 'auth', 'role:manager,producer'])->get('/probe/either', fn () => 'either area');
    Route::middleware(['web'])->get('/probe/not-allowed', fn () => throw new NotAllowed('Nope.'));
    Route::middleware(['web'])->post('/probe/domain-error', fn () => throw new ProbeFailed('raw message'));
});

it('sends guests to the login page', function () {
    $this->get('/probe/manage')->assertRedirect(route('login'));
});

it('opens a role area to accounts holding that role', function () {
    $this->actingAs(userWithRoles(Role::Manager))->get('/probe/manage')->assertOk()->assertSee('manager area');
});

it('answers 403 to an account without the role', function () {
    $this->actingAs(userWithRoles(Role::Member))->get('/probe/manage')->assertForbidden();
});

it('answers 403 to an account with no role at all', function () {
    $this->actingAs(userWithRoles())->get('/probe/member')->assertForbidden();
});

it('lets one login reach several role areas (S44)', function () {
    $this->actingAs(userWithRoles(Role::Member, Role::Manager));

    $this->get('/probe/member')->assertOk();
    $this->get('/probe/manage')->assertOk();
    $this->get('/probe/producer')->assertForbidden();
});

it('accepts any of several roles listed in the middleware', function () {
    $this->actingAs(userWithRoles(Role::Producer))->get('/probe/either')->assertOk();
    $this->actingAs(userWithRoles(Role::Member))->get('/probe/either')->assertForbidden();
});

it('answers 403, not 500, for an unknown role name in the middleware', function () {
    Route::middleware(['web', 'auth', 'role:manger'])->get('/probe/typo', fn () => 'typo area');

    $this->actingAs(userWithRoles(Role::Manager))->get('/probe/typo')->assertForbidden();
});

it('maps NotAllowed to 403', function () {
    $this->get('/probe/not-allowed')->assertForbidden();
});

it('maps a domain error to a 422 with its translated message', function () {
    app('translator')->addLines(['errors.ProbeFailed' => 'The probe failed.'], 'en');

    $this->postJson('/probe/domain-error')
        ->assertUnprocessable()
        ->assertJsonPath('errors.domain.0', 'The probe failed.');
});

it('falls back to the error message when there is no translation', function () {
    $this->postJson('/probe/domain-error')
        ->assertUnprocessable()
        ->assertJsonPath('errors.domain.0', 'raw message');
});

it('shows a domain error as a form error on an Inertia request', function () {
    $this->from('/somewhere')
        ->post('/probe/domain-error', [], ['X-Inertia' => 'true'])
        ->assertRedirect('/somewhere')
        ->assertSessionHasErrors(['domain' => 'raw message']);
});

it('shares the account roles and role areas with every page', function () {
    $this->actingAs(userWithRoles(Role::Member, Role::Manager))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['manager', 'member'])
            ->has('roleAreas', 2)
            ->where('roleAreas.0.role', 'member')
            ->where('roleAreas.1.role', 'manager'));
});

it('shares a single role area for an account with one role', function () {
    $this->actingAs(userWithRoles(Role::Producer))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('roleAreas', 1)->where('roleAreas.0.role', 'producer'));
});

it('resolves the actor with UUID account, household and producer ids', function () {
    $householdId = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $householdId, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $producerId = (string) Str::uuid7();
    $user = userWithRoles(Role::Producer);
    DB::table('users')->where('id', $user->id)->update(['household_id' => $householdId, 'producer_id' => $producerId]);

    $actor = app(ActorResolver::class)->forAccount($user->id);

    expect($user->id)->toBeUuid()
        ->and($actor->accountId)->toBe($user->id)
        ->and($actor->householdId)->toBe($householdId)
        ->and($actor->producerId)->toBe($producerId);
});
