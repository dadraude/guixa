<?php

use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\Error\ReadUnavailable;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FrozenClock;

beforeEach(function () {
    $this->withoutVite();
    $this->app->instance(Clock::class, new FrozenClock('2026-11-12 09:30', config('guixa.timezone')));
});

it('sends the successful-read time with readFailed false on a normal read (R15, S11)', function () {
    $this->actingAs(accountWith(Role::Manager));

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('readFailed', false)
        ->where('lastUpdatedAt', '2026-11-12T09:30:00+01:00')
        ->has('attention')
        ->has('opportunities'));
});

it('sends null sections and time with readFailed true when a known source read fails, never an empty list (R15, S11)', function () {
    $this->actingAs(accountWith(Role::Manager));
    $this->app->instance(BasketWorkRepository::class, new class implements BasketWorkRepository
    {
        public function memberCollection(string $householdId): array
        {
            throw ReadUnavailable::source('baskets');
        }

        public function staffCollection(): array
        {
            throw ReadUnavailable::source('baskets');
        }

        public function staffDrafts(): array
        {
            throw ReadUnavailable::source('baskets');
        }
    });

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('readFailed', true)
        ->where('lastUpdatedAt', null)
        ->where('attention', null)
        ->where('opportunities', null)
        ->where('can', ['showCaughtUp' => false, 'showNoContext' => false]));
});

it('keeps an authorization failure as 403 instead of reading it as a failed read (R15)', function () {
    $this->actingAs(accountWith(Role::Manager));
    $this->app->instance(BasketWorkRepository::class, new class implements BasketWorkRepository
    {
        public function memberCollection(string $householdId): array
        {
            throw new AuthorizationException;
        }

        public function staffCollection(): array
        {
            throw new AuthorizationException;
        }

        public function staffDrafts(): array
        {
            throw new AuthorizationException;
        }
    });

    $this->get(route('dashboard'))->assertForbidden();
});

/** A closed cycle whose baskets A and B are validated and waiting for collection. */
function waitingCollections(object $test): array
{
    $week = lateArrivalWeek(withSpareDraft: false);
    $test->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    app('auth')->forgetGuards();

    return $week;
}

function attentionFor(object $test, $user): array
{
    app('auth')->forgetGuards();

    return json_decode(json_encode($test->actingAs($user)->get('/dashboard')->viewData('page')['props']['attention']), true);
}

it('drops the member collection group on the next read after an unlink, and the stale link refuses (R1, S12)', function () {
    $week = waitingCollections($this);
    $member = memberOf($week['households']['A']);
    $groups = attentionFor($this, $member);
    expect($groups)->toHaveCount(1);
    $staleLink = $groups[0]['href'];

    DB::table('users')->where('id', $member->id)->update(['household_id' => null]);

    expect(attentionFor($this, $member->fresh()))->toBe([]);
    $this->get($staleLink)->assertForbidden();
});

it('drops the staff-wide groups on the next read after Finance is removed, and the stale link refuses (R1, S12)', function () {
    $week = waitingCollections($this);
    $finance = accountWith(Role::Finance);
    $groups = attentionFor($this, $finance);
    expect($groups)->toHaveCount(1);
    $staleLink = $groups[0]['href'];

    DB::table('account_roles')->where('user_id', $finance->id)->where('role', Role::Finance->value)->delete();

    expect(attentionFor($this, $finance->fresh()))->toBe([]);
    $this->get($staleLink)->assertForbidden();
});
