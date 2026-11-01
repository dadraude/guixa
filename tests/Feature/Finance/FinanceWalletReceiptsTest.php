<?php

use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Wallet\Application\ChargeForBasket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function receiptHousehold(): string
{
    $id = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $id, 'name' => 'La Figuera', 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

it('records one global Finance receipt over two basket charges and a linked immutable correction (S85, R21, R29-R32)', function () {
    $finance = accountWith(Role::Finance);
    $household = receiptHousehold();
    $actor = new Actor($finance->id, [Role::Finance]);
    $first = app(CreateStockBasket::class)($actor, $household);
    $second = app(CreateStockBasket::class)($actor, $household);
    app(ChargeForBasket::class)($actor, $household, $first, 1200);
    app(ChargeForBasket::class)($actor, $household, $second, 1800);

    $this->actingAs($finance)->get("/finance/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page->component('Wallet/Statement')
            ->where('canManageReceipts', true)->where('wallet.balance', '-30.00')
            ->has('wallet.movements', 2));
    $this->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '50', 'note' => 'cash'])->assertRedirect();
    $receipt = DB::table('wallet_movements')->where('household_id', $household)->where('type', 'top_up')->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->amount_cents)->toBe(5000)
        ->and($receipt->recorded_by)->toBe($finance->id)
        ->and($receipt->basket_id)->toBeNull();
    $this->get("/finance/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page->where('wallet.balance', '20.00')
            ->has('wallet.movements', 3)->where('wallet.movements.0.id', $receipt->id));

    $correctionId = (string) Str::uuid7();
    $payload = ['amount' => '-50', 'note' => 'duplicated', 'source_movement_id' => $receipt->id, 'correction_id' => $correctionId];
    $this->post("/finance/households/{$household}/wallet/corrections", $payload)->assertRedirect();
    $this->post("/finance/households/{$household}/wallet/corrections", $payload)->assertRedirect();
    $this->post("/finance/households/{$household}/wallet/corrections", [...$payload, 'amount' => '-30'])
        ->assertSessionHasErrors(['domain' => 'Aquest identificador de correcció ja s’ha utilitzat per a un altre moviment.']);
    $this->get("/finance/households/{$household}/wallet")
        ->assertInertia(fn (Assert $page) => $page->where('wallet.balance', '-30.00')
            ->has('wallet.movements', 4)
            ->where('wallet.movements.0.sourceMovementId', $receipt->id)
            ->where('wallet.movements.0.amount', '-50.00')
            ->where('wallet.movements.0.note', 'duplicated'));
    expect(DB::table('wallet_movements')->where('id', $correctionId)->count())->toBe(1)
        ->and(DB::table('wallet_movements')->where('id', $receipt->id)->value('amount_cents'))->toBe(5000)
        ->and(DB::table('wallet_movements')->whereIn('basket_id', [$first, $second])->count())->toBe(2);
});

it('rejects missing or foreign receipt sources, zero and missing reason without new movements (S24, R32)', function () {
    $finance = accountWith(Role::Finance);
    $own = receiptHousehold();
    $other = receiptHousehold();
    $this->actingAs($finance)->post("/finance/households/{$other}/wallet/top-ups", ['amount' => '5'])->assertRedirect();
    $foreign = DB::table('wallet_movements')->where('household_id', $other)->value('id');
    $url = "/finance/households/{$own}/wallet/corrections";
    $base = ['amount' => '1', 'note' => 'duplicate', 'correction_id' => (string) Str::uuid7()];
    $this->post($url, $base)->assertSessionHasErrors('source_movement_id');
    $this->post($url, [...$base, 'source_movement_id' => (string) Str::uuid7()])
        ->assertSessionHasErrors(['domain' => 'L’ingrés original no existeix o pertany a una altra unitat de convivència.']);
    $this->post($url, [...$base, 'source_movement_id' => $foreign])
        ->assertSessionHasErrors(['domain' => 'L’ingrés original no existeix o pertany a una altra unitat de convivència.']);
    $this->post($url, [...$base, 'source_movement_id' => $foreign, 'amount' => '0'])->assertSessionHasErrors('domain');
    $this->post($url, [...$base, 'source_movement_id' => $foreign, 'note' => ''])->assertSessionHasErrors('note');
    $this->post($url, [...$base, 'source_movement_id' => $foreign, 'cycle_id' => (string) Str::uuid7()])->assertSessionHasErrors('cycle_id');
    expect(DB::table('wallet_movements')->where('household_id', $own)->count())->toBe(0);
});

it('denies receipt writes to members and immediately after Finance revocation (S84, R36c)', function () {
    $household = receiptHousehold();
    $member = memberOf($household);
    $manager = accountWith(Role::Manager);
    $finance = accountWith();
    $receiptUrl = "/finance/households/{$household}/wallet/top-ups";

    $this->actingAs($member)->post($receiptUrl, ['amount' => '5'])->assertForbidden();
    $this->actingAs($manager)->put(route('manage.accounts.finance-role.update', $finance->id))->assertRedirect();
    $this->actingAs($finance)->post($receiptUrl, ['amount' => '5'])->assertRedirect();
    $this->actingAs($manager)->delete(route('manage.accounts.finance-role.destroy', $finance->id))->assertRedirect();
    $this->actingAs($finance)->post($receiptUrl, ['amount' => '5'])->assertForbidden();
    expect(DB::table('wallet_movements')->where('household_id', $household)->where('type', 'top_up')->count())->toBe(1);
});

it('retains an independent receipt when a basket validation fails (S19, R29)', function () {
    $finance = accountWith(Role::Finance);
    $household = receiptHousehold();
    $actor = new Actor($finance->id, [Role::Finance]);
    $basket = app(CreateStockBasket::class)($actor, $household);

    $this->actingAs($finance)->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '40'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertSessionHasErrors('domain');
    expect(DB::table('wallet_movements')->where('household_id', $household)->count())->toBe(1)
        ->and(DB::table('wallet_movements')->where('household_id', $household)->value('amount_cents'))->toBe(4000);
});
