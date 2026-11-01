<?php

use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('renders an own persisted basket and refuses a forged other-household read and all member mutations (R19a, R21, R23a, R35)', function () {
    $manager = accountWith(Role::Manager);
    $mine = (string) Str::uuid7();
    $other = (string) Str::uuid7();
    foreach ([$mine, $other] as $household) {
        DB::table('households')->insert(['id' => $household, 'name' => 'Household '.$household, 'created_at' => now(), 'updated_at' => now()]);
    }
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $ownBasket = app(CreateStockBasket::class)(Actor::manager($manager->id), $mine);
    $otherBasket = app(CreateStockBasket::class)(Actor::manager($manager->id), $other);
    $this->actingAs($manager)->post("/finance/baskets/{$ownBasket}/return-lines", [
        'return_item_id' => $item, 'return_quantity' => '2',
    ])->assertRedirect();

    $this->actingAs(memberOf($mine))->get('/baskets')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Member/Baskets/Index')->has('baskets', 1)->where('baskets.0.id', $ownBasket));
    $this->get("/baskets/{$ownBasket}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Member/Baskets/Show')->where('basket.id', $ownBasket)
        ->where('basket.returnLines.0.name', 'Glass jar')->where('basket.returnLines.0.creditCents', 100)
        ->where('basket.totalCents', -100)->where('basket.projectedBalanceCents', 100));
    $this->get("/baskets/{$otherBasket}")->assertNotFound();
    $this->post('/finance/baskets', ['household_id' => $mine])->assertForbidden();
    $this->post("/finance/baskets/{$ownBasket}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertForbidden();
    $this->post("/finance/baskets/{$ownBasket}/validation")->assertForbidden();
    $this->put("/finance/baskets/{$ownBasket}/collection")->assertForbidden();
    $this->post("/finance/baskets/{$ownBasket}/cancellation")->assertForbidden();
    $this->post("/finance/households/{$mine}/wallet/top-ups", ['amount' => '1'])->assertForbidden();
    expect(DB::table('basket_lines')->where('basket_id', $ownBasket)->value('quantity'))->toBe('2.000');
});

it('shows empty, cancelled, validated and collected real contents with immutable validation values (R21, R23a, R24a)', function () {
    $manager = accountWith(Role::Manager);
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
    $member = memberOf($household);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $empty = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $cancelled = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $validated = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $collected = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $this->actingAs($manager)->post("/finance/baskets/{$cancelled}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$cancelled}/cancellation")->assertRedirect();
    foreach ([$validated, $collected] as $id) {
        $this->post("/finance/baskets/{$id}/return-lines", ['return_item_id' => $item, 'return_quantity' => '2'])->assertRedirect();
        $this->post("/finance/baskets/{$id}/validation")->assertRedirect();
    }
    $this->put("/finance/baskets/{$collected}/collection")->assertRedirect();
    DB::table('return_items')->where('id', $item)->update(['name' => 'Changed jar', 'amount_cents' => 75]);

    $this->actingAs($member)->get('/baskets')->assertInertia(fn (Assert $page) => $page
        ->component('Member/Baskets/Index')->has('baskets', 4));
    $this->get("/baskets/{$empty}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'draft')->where('basket.totalCents', 0)->has('basket.returnLines', 0));
    $this->get("/baskets/{$cancelled}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'cancelled')->where('basket.totalCents', 0)->has('basket.returnLines', 0));
    $this->get("/baskets/{$validated}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'validated')->where('basket.totalCents', -100)
        ->where('basket.returnLines.0.name', 'Glass jar')->where('basket.returnLines.0.amountCents', 50)
        ->where('basket.validatedAt', fn ($value) => $value !== null));
    $this->get("/baskets/{$collected}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'collected')->where('basket.totalCents', -100)
        ->where('basket.returnLines.0.name', 'Glass jar')
        ->where('basket.collectedAt', fn ($value) => $value !== null));
    $this->get('/wallet')->assertInertia(fn (Assert $page) => $page
        ->where('wallet.movements.0.basketId', $collected)->where('wallet.movements.1.basketId', $validated));
});

it('drops old household basket access after move, unlink and account deactivation (R35)', function () {
    $manager = accountWith(Role::Manager);
    $old = (string) Str::uuid7();
    $new = (string) Str::uuid7();
    foreach ([$old, $new] as $household) {
        DB::table('households')->insert(['id' => $household, 'name' => 'Household '.$household, 'created_at' => now(), 'updated_at' => now()]);
    }
    $basket = app(CreateStockBasket::class)(Actor::manager($manager->id), $old);
    $member = memberOf($old);
    $this->actingAs($member)->get("/baskets/{$basket}")->assertOk();
    $this->actingAs($manager)->put(route('manage.accounts.household.update', $member->id), ['household_id' => $new])->assertRedirect();
    $this->actingAs($member)->get("/baskets/{$basket}")->assertNotFound();
    $this->get('/baskets')->assertInertia(fn (Assert $page) => $page->has('baskets', 0));
    $this->actingAs($manager)->delete(route('manage.accounts.household.destroy', $member->id))->assertRedirect();
    $this->actingAs($member)->get('/baskets')->assertForbidden();
    $this->get("/baskets/{$basket}")->assertForbidden();
    $this->actingAs($manager)->put(route('manage.accounts.household.update', $member->id), ['household_id' => $old])->assertRedirect();
    $this->actingAs($member)->get("/baskets/{$basket}")->assertOk();
    $this->actingAs($manager)->post(route('manage.accounts.deactivation.store', $member->id))->assertRedirect();
    $this->actingAs($member->fresh())->get("/baskets/{$basket}")->assertRedirect(route('login'));
    $this->actingAs($member->fresh())->get('/baskets')->assertRedirect(route('login'));
});

it('keeps the member basket routes limited to current members and read methods (R19a, R35)', function () {
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household H', 'created_at' => now(), 'updated_at' => now()]);
    $basket = app(CreateStockBasket::class)(Actor::manager(accountWith(Role::Manager)->id), $household);

    $this->get('/baskets')->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Producer))->get('/baskets')->assertForbidden();
    $this->actingAs(accountWith(Role::Finance))->get("/baskets/{$basket}")->assertForbidden();
    $this->actingAs(accountWith(Role::Manager))->get('/baskets')->assertForbidden();
    $this->actingAs(memberOf($household))->post('/baskets', [])->assertMethodNotAllowed();
    $this->put("/baskets/{$basket}", [])->assertMethodNotAllowed();
    $this->delete("/baskets/{$basket}")->assertMethodNotAllowed();
    $this->get('/baskets/not-a-uuid')->assertNotFound();
});
