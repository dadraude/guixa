<?php

use App\Models\User;
use Guixa\Catalog\Application\DeactivateProducer;
use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Membership\Application\CanLogIn;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;

function deactivationJordi(): ProducerModel
{
    return ProducerModel::query()->create([
        'name' => 'Jordi', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
}

function deactivationAccountOf(ProducerModel $producer, Role ...$extraRoles): User
{
    $user = User::factory()->create(['producer_id' => $producer->id]);

    foreach ([Role::Producer, ...$extraRoles] as $role) {
        DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => $role->value]);
    }

    return $user;
}

function deactivationCycleWith(ProducerModel $producer, string $pickupDate, bool $participating = true): string
{
    $id = (string) fake()->uuid();
    DB::table('cycles')->insert(['id' => $id, 'pickup_date' => $pickupDate]);
    DB::table('cycle_producers')->insert([
        'cycle_id' => $id, 'producer_id' => $producer->id, 'participating' => $participating, 'closes_at' => null,
    ]);

    return $id;
}

function deactivationParticipating(string $cycleId, ProducerModel $producer): bool
{
    return (bool) DB::table('cycle_producers')->where('cycle_id', $cycleId)->where('producer_id', $producer->id)->value('participating');
}

it('keeps deactivating a producer and changing participation to managers (R3a, R6c)', function (string $method, string $uri) {
    $jordi = deactivationJordi();
    $cycleId = deactivationCycleWith($jordi, now()->addDays(10)->toDateString());
    $uri = str_replace(['{cycle}', '{producer}'], [$cycleId, $jordi->id], $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['post', '/manage/producers/{producer}/deactivation'],
    ['delete', '/manage/producers/{producer}/deactivation'],
    ['put', '/manage/cycles/{cycle}/producers/{producer}'],
    ['delete', '/manage/cycles/{cycle}/producers/{producer}'],
]);

it('deactivates a producer: no login, no new participation, stops in the cycle, then restores the role (R3a, S50)', function () {
    $manager = accountWith(Role::Manager);
    $jordi = deactivationJordi();
    $account = deactivationAccountOf($jordi);
    $cycleA = deactivationCycleWith($jordi, now()->addDays(10)->toDateString());
    expect(app(CanLogIn::class)($account->id))->toBeTrue();

    $this->actingAs($manager)->post("/manage/producers/{$jordi->id}/deactivation")->assertRedirect();

    expect($jordi->fresh()->active)->toBeFalse()
        ->and(app(CanLogIn::class)($account->id))->toBeFalse()
        ->and(DB::table('account_roles')->where('user_id', $account->id)->count())->toBe(0)
        ->and(deactivationParticipating($cycleA, $jordi))->toBeFalse();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleA}/producers/{$jordi->id}")->assertSessionHasErrors('domain');
    expect(deactivationParticipating($cycleA, $jordi))->toBeFalse();

    $this->actingAs($manager)->delete("/manage/producers/{$jordi->id}/deactivation")->assertRedirect();

    expect($jordi->fresh()->active)->toBeTrue()
        ->and(app(CanLogIn::class)($account->id))->toBeTrue()
        ->and(deactivationParticipating($cycleA, $jordi))->toBeFalse();
});

it('keeps the other roles of a deactivated producer account, which can still log in (R3a)', function () {
    $jordi = deactivationJordi();
    $account = deactivationAccountOf($jordi, Role::Manager);

    $this->actingAs(accountWith(Role::Manager))->post("/manage/producers/{$jordi->id}/deactivation");

    expect(DB::table('account_roles')->where('user_id', $account->id)->pluck('role')->all())->toBe([Role::Manager->value])
        ->and(app(CanLogIn::class)($account->id))->toBeTrue();
});

it('does not touch the cycles of another producer, nor a cancelled cycle (R3a)', function () {
    $jordi = deactivationJordi();
    $other = ProducerModel::query()->create(['name' => 'Other', 'active' => true, 'cutoff_days_before' => 2, 'cutoff_time' => '12:00']);
    $mine = deactivationCycleWith($jordi, now()->addDays(10)->toDateString());
    $theirs = deactivationCycleWith($other, now()->addDays(11)->toDateString());
    $cancelled = deactivationCycleWith($jordi, now()->addDays(12)->toDateString());
    DB::table('cycles')->where('id', $cancelled)->update(['cancelled_at' => now()]);

    $this->actingAs(accountWith(Role::Manager))->post("/manage/producers/{$jordi->id}/deactivation");

    expect(deactivationParticipating($mine, $jordi))->toBeFalse()
        ->and(deactivationParticipating($theirs, $other))->toBeTrue()
        ->and(deactivationParticipating($cancelled, $jordi))->toBeTrue();
});

it('stops and resumes a producer in one cycle (R6c)', function () {
    $manager = accountWith(Role::Manager);
    $jordi = deactivationJordi();
    $cycleId = deactivationCycleWith($jordi, now()->addDays(10)->toDateString());

    $this->actingAs($manager)->delete("/manage/cycles/{$cycleId}/producers/{$jordi->id}")->assertRedirect();
    expect(deactivationParticipating($cycleId, $jordi))->toBeFalse();

    $this->actingAs($manager)->put("/manage/cycles/{$cycleId}/producers/{$jordi->id}")->assertRedirect();
    expect(deactivationParticipating($cycleId, $jordi))->toBeTrue();
});

it('rolls the whole deactivation back when a step fails (R3a, D13)', function () {
    $jordi = deactivationJordi();
    $cycleId = deactivationCycleWith($jordi, now()->addDays(10)->toDateString());
    app()->bind(ProducerCycles::class, fn () => new class implements ProducerCycles
    {
        public function hasUnsettledLines(string $productId): bool
        {
            return false;
        }

        public function stopParticipatingWhereNotSent(string $producerId): void
        {
            throw new RuntimeException('boom');
        }
    });
    $account = deactivationAccountOf($jordi);

    expect(fn () => app(DeactivateProducer::class)(
        Actor::manager('m'),
        $jordi->id,
    ))->toThrow(RuntimeException::class);

    expect($jordi->fresh()->active)->toBeTrue()
        ->and(app(CanLogIn::class)($account->id))->toBeTrue()
        ->and(deactivationParticipating($cycleId, $jordi))->toBeTrue();
});
