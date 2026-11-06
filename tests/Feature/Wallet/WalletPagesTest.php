<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function householdH(): string
{
    $id = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $id, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

it('keeps the household wallet routes to managers and finance', function (string $method, string $uri) {
    $uri = str_replace('{id}', householdH(), $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/finance/households/{id}/wallet'],
    ['post', '/finance/households/{id}/wallet/top-ups'],
    ['post', '/finance/households/{id}/wallet/corrections'],
]);

it('keeps the member wallet to members', function () {
    $this->get('/wallet')->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Producer))->get('/wallet')->assertForbidden();
});

it('records a top-up and a correction and shows both to the member (S24, R31)', function () {
    $household = householdH();
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '50,00', 'note' => 'cash'])->assertRedirect();
    $receiptId = DB::table('wallet_movements')->where('household_id', $household)->where('type', 'top_up')->value('id');
    $correctionId = (string) Str::uuid7();
    $this->actingAs($manager)->post("/finance/households/{$household}/wallet/corrections", [
        'amount' => '-50', 'note' => 'duplicated', 'source_movement_id' => $receiptId, 'correction_id' => $correctionId,
    ])->assertRedirect();
    expect(DB::table('wallet_movements')->where('id', $correctionId)->value('source_movement_id'))->toBe($receiptId);

    $this->actingAs(memberOf($household))->get('/wallet')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Wallet/Statement')
            ->where('wallet.balance', '0.00')
            ->has('wallet.movements', 2)
            ->where('wallet.movements.0.type', 'correction')
            ->where('wallet.movements.0.amount', '-50.00')
            ->where('wallet.movements.0.sourceMovementId', $receiptId)
            ->where('wallet.movements.0.recordedBy', $manager->id)
            ->where('wallet.movements.1.type', 'top_up')
            ->where('wallet.movements.1.note', 'cash')
            ->where('household', null));
});

it('shows a manager the household wallet with its name and the way back to the household', function () {
    $household = householdH();

    $this->actingAs(accountWith(Role::Manager))->get("/finance/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Wallet/Statement')
            ->where('household.name', 'Household H')
            ->where('householdReturnHref', route('manage.households.show', ['household' => $household]))
            ->where('wallet.balance', '0.00')
            ->has('wallet.movements', 0));
});

it('shows only the members own household and refuses an account without one', function () {
    $mine = householdH();
    $other = householdH();
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/finance/households/{$other}/wallet/top-ups", ['amount' => '9']);

    $this->actingAs(memberOf($mine))->get('/wallet')->assertInertia(fn (Assert $page) => $page->where('wallet.balance', '0.00'));
    $this->actingAs(accountWith())->get('/wallet')->assertForbidden();
});

it('shows the domain error for a top-up of zero and a correction without a note', function () {
    $household = householdH();
    $this->actingAs(accountWith(Role::Manager));

    $this->from("/finance/households/{$household}/wallet")
        ->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '0'])
        ->assertSessionHasErrors('domain');
    $receipt = DB::table('wallet_movements')->where('household_id', $household)->where('type', 'top_up')->value('id');
    if ($receipt === null) {
        $this->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '5'])->assertRedirect();
        $receipt = DB::table('wallet_movements')->where('household_id', $household)->where('type', 'top_up')->value('id');
    }
    $this->post("/finance/households/{$household}/wallet/corrections", ['amount' => '5', 'note' => '', 'source_movement_id' => $receipt, 'correction_id' => (string) Str::uuid7()])
        ->assertSessionHasErrors('note');
    $this->post("/finance/households/{$household}/wallet/corrections", ['amount' => '0', 'note' => 'typo', 'source_movement_id' => $receipt, 'correction_id' => (string) Str::uuid7()])
        ->assertSessionHasErrors('domain');

    expect(DB::table('wallet_movements')->where('type', 'correction')->count())->toBe(0);
});

it('answers 404 to an unknown household and to an id that is not a UUID', function (string $method, string $uri) {
    $this->actingAs(accountWith(Role::Manager))->{$method}(str_replace('{id}', (string) Str::uuid7(), $uri), ['amount' => '5', 'note' => 'x', 'source_movement_id' => (string) Str::uuid7(), 'correction_id' => (string) Str::uuid7()])->assertNotFound();
})->with([
    ['get', '/finance/households/{id}/wallet'],
    ['post', '/finance/households/{id}/wallet/top-ups'],
    ['post', '/finance/households/{id}/wallet/corrections'],
]);

it('answers 404 to a household id that is not a UUID', function () {
    $this->actingAs(accountWith(Role::Manager))->get('/finance/households/not-a-uuid/wallet')->assertNotFound();
});

it('shows each movement date in the cooperative timezone, not in UTC (Time)', function () {
    config(['guixa.timezone' => 'Europe/Madrid']);
    $household = householdH();
    $manager = accountWith(Role::Manager);
    DB::table('wallet_movements')->insert([
        'id' => (string) Str::uuid7(), 'household_id' => $household, 'type' => 'top_up', 'amount_cents' => 1000,
        'occurred_at' => '2026-10-22 22:30:00', 'recorded_by' => $manager->id,
    ]);

    $this->actingAs($manager)->get("/finance/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page->where('wallet.movements.0.occurredAt', '2026-10-23 00:30'));
});

it('retires the manager copy of the household wallet: every method answers 404 (T72)', function (string $method, string $suffix) {
    $household = householdH();

    $this->actingAs(accountWith(Role::Manager))->call(strtoupper($method), "/manage/households/{$household}/wallet{$suffix}", ['amount' => '5'])->assertNotFound();
    expect(Route::has('manage.households.wallet.show'))->toBeFalse();
})->with([
    ['get', ''],
    ['post', '/top-ups'],
    ['post', '/corrections'],
]);

it('gives Finance-only the wallet without a way back to the manager household page', function () {
    $household = householdH();

    $this->actingAs(accountWith(Role::Finance))->get("/finance/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page->component('Wallet/Statement')->missing('householdReturnHref'));
});
