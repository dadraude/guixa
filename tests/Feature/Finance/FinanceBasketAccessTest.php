<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\GetBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('grants Finance on an existing account for the next real basket request and public use case, then revokes it (S84)', function () {
    $manager = accountWith(Role::Manager);
    $finance = accountWith();
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $basket = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);

    $this->actingAs($manager)->put(route('manage.accounts.finance-role.update', $finance->id))->assertRedirect();
    $this->actingAs($finance)->get("/finance/baskets/{$basket}")
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Finance/Baskets/Show')->where('basket.id', $basket));
    $actor = app(ActorResolver::class)->forAccount($finance->id);
    expect($actor->hasRole(Role::Finance))->toBeTrue();
    expect(app(GetBasket::class)($actor, $basket)->id)->toBe($basket);

    $this->actingAs($manager)->delete(route('manage.accounts.finance-role.destroy', $finance->id))->assertRedirect();
    $this->actingAs($finance)->get("/finance/baskets/{$basket}")->assertForbidden();
});

it('lets Finance-only create, add, validate and collect an independent basket with actor attribution (S79, S84)', function () {
    $manager = accountWith(Role::Manager);
    $finance = accountWith(Role::Finance);
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $producer = ProducerModel::query()->create([
        'name' => 'Oil maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'stock_product_id' => $product,
        'type' => 'entry', 'quantity' => '2', 'occurred_at' => now(), 'recorded_by' => $manager->id]);

    $this->actingAs($finance)->get('/finance/baskets')->assertOk();
    $this->get('/finance/baskets/create')->assertOk();
    $this->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->component('Finance/Baskets/Show')->where('stockProducts.0.id', $product));
    $this->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page->where('basket.state', 'collected'));
    $this->get("/finance/households/{$household}/wallet")->assertInertia(fn (Assert $page) => $page
        ->component('Wallet/Statement')->where('canManageReceipts', false)->where('wallet.movements.0.basketId', $basket));
    expect(DB::table('stock_movements')->where('basket_id', $basket)->value('recorded_by'))->toBe($finance->id)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->value('recorded_by'))->toBe($finance->id);
});

it('keeps Finance out of account, catalogue, cycle, reception, stock administration and receipt mutations (R19a, R36c, S84)', function () {
    $finance = accountWith(Role::Finance);
    $this->actingAs($finance)->get('/manage/accounts')->assertForbidden();
    $this->get('/manage/stock')->assertForbidden();
    $this->get('/manage/cycles')->assertForbidden();
    $this->get('/manage/producers')->assertForbidden();
    $this->get('/manage/return-items')->assertForbidden();
    $this->put('/manage/accounts/'.$finance->id.'/finance-role')->assertForbidden();
    $this->post('/manage/households/'.Str::uuid7().'/wallet/top-ups', ['amount' => '1'])->assertForbidden();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('auth.roles', ['finance'])->has('roleAreas', 1)->where('roleAreas.0.role', 'finance'));
});

it('preserves member household privacy and combined Manager access (R31, R36a, S84)', function () {
    $household = (string) Str::uuid7();
    $other = (string) Str::uuid7();
    foreach ([$household, $other] as $id) {
        DB::table('households')->insert(['id' => $id, 'name' => 'Home', 'created_at' => now(), 'updated_at' => now()]);
    }
    $member = memberOf($household);
    $this->actingAs($member)->get("/finance/households/{$other}/wallet")->assertForbidden();
    $this->get('/wallet')->assertOk();
    $combined = accountWith(Role::Finance, Role::Manager);
    $this->actingAs($combined)->get("/finance/households/{$other}/wallet")->assertOk();
    $this->get('/manage/accounts')->assertOk();
});

it('lets an activated Finance-only account sign in after grant and denies sign-in after revocation (R36c, S84)', function () {
    $manager = accountWith(Role::Manager);
    $finance = accountWith();
    $this->actingAs($manager)->put(route('manage.accounts.finance-role.update', $finance->id))->assertRedirect();
    auth()->logout();

    $this->post(route('login.store'), ['email' => $finance->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($finance);
    auth()->logout();

    $this->actingAs($manager)->delete(route('manage.accounts.finance-role.destroy', $finance->id))->assertRedirect();
    auth()->logout();
    $this->post(route('login.store'), ['email' => $finance->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('shows the granted and revoked Finance state on the existing Manager account page (R36c)', function () {
    $manager = accountWith(Role::Manager);
    $manager->forceFill(['name' => 'Alpha Manager'])->save();
    $finance = accountWith();
    $finance->forceFill(['name' => 'Zulu Finance'])->save();
    $this->actingAs($manager)->put(route('manage.accounts.finance-role.update', $finance->id))->assertRedirect();
    $this->get(route('manage.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Manage/Accounts/Index')->where('accounts.1.roles', ['finance']));
    $this->delete(route('manage.accounts.finance-role.destroy', $finance->id))->assertRedirect();
    $this->get(route('manage.accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('accounts.1.roles', []));
});
