<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function validatedReturnBasket(string $managerId, string $household, string $itemId): string
{
    $basket = app(CreateStockBasket::class)(Actor::manager($managerId), $household);
    test()->post("/finance/baskets/{$basket}/return-lines", ['return_item_id' => $itemId, 'return_quantity' => '2'])->assertRedirect();
    test()->post("/finance/baskets/{$basket}/validation")->assertRedirect();

    return $basket;
}

it('lets a member collect their own validated basket over HTTP and refuses the identical forged request (R23b, R35, S82)', function () {
    $manager = accountWith(Role::Manager);
    [$mine, $other] = [(string) Str::uuid7(), (string) Str::uuid7()];
    foreach ([$mine, $other] as $household) {
        DB::table('households')->insert(['id' => $household, 'name' => 'Household '.$household, 'created_at' => now(), 'updated_at' => now()]);
    }
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $this->actingAs($manager);
    $ownBasket = validatedReturnBasket($manager->id, $mine, $item);
    $otherBasket = validatedReturnBasket($manager->id, $other, $item);
    $anna = memberOf($mine, 'Anna');
    $ledger = fn () => [DB::table('wallet_movements')->orderBy('id')->get(), DB::table('stock_movements')->orderBy('id')->get()];
    $before = $ledger();

    $this->actingAs($anna)->put("/baskets/{$otherBasket}/collection")->assertForbidden();
    expect(DB::table('baskets')->where('id', $otherBasket)->value('state'))->toBe('validated');

    $this->put("/baskets/{$ownBasket}/collection")->assertRedirect();
    $first = DB::table('baskets')->where('id', $ownBasket)->first();
    $this->actingAs(memberOf($mine, 'Berta'))->put("/baskets/{$ownBasket}/collection")->assertRedirect();

    $after = DB::table('baskets')->where('id', $ownBasket)->first();
    expect($after->state)->toBe('collected')
        ->and($after->collected_by)->toBe($anna->id)
        ->and($after->collected_at)->toBe($first->collected_at)
        ->and(DB::table('basket_collection_events')->where('basket_id', $ownBasket)->count())->toBe(1)
        ->and($ledger())->toEqual($before);
    $this->get("/baskets/{$ownBasket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'collected')->where('basket.collectionEvents.0.recordedBy', fn ($value) => $value !== null));
});

it('denies collection after a move, unlink or deactivation and for non-member roles (R35)', function () {
    $manager = accountWith(Role::Manager);
    [$old, $new] = [(string) Str::uuid7(), (string) Str::uuid7()];
    foreach ([$old, $new] as $household) {
        DB::table('households')->insert(['id' => $household, 'name' => 'Household '.$household, 'created_at' => now(), 'updated_at' => now()]);
    }
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $this->actingAs($manager);
    $basket = validatedReturnBasket($manager->id, $old, $item);
    $member = memberOf($old);
    $state = fn () => DB::table('baskets')->where('id', $basket)->value('state');

    $this->put(route('manage.accounts.household.update', $member->id), ['household_id' => $new])->assertRedirect();
    $this->actingAs($member)->put("/baskets/{$basket}/collection")->assertForbidden();
    $this->actingAs($manager)->delete(route('manage.accounts.household.destroy', $member->id))->assertRedirect();
    $this->actingAs($member)->put("/baskets/{$basket}/collection")->assertForbidden();
    $this->actingAs($manager)->put(route('manage.accounts.household.update', $member->id), ['household_id' => $old])->assertRedirect();
    $this->actingAs($manager)->post(route('manage.accounts.deactivation.store', $member->id))->assertRedirect();
    $this->actingAs($member->fresh())->put("/baskets/{$basket}/collection")->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Producer))->put("/baskets/{$basket}/collection")->assertForbidden();
    $this->actingAs(accountWith(Role::Finance))->put("/baskets/{$basket}/collection")->assertForbidden();
    expect($state())->toBe('validated');
});

it('refuses members every other basket operation (R19a, R23b)', function () {
    $manager = accountWith(Role::Manager);
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $this->actingAs($manager);
    $basket = validatedReturnBasket($manager->id, $household, $item);
    $this->actingAs(memberOf($household));

    $this->post("/finance/baskets/{$basket}/validation")->assertForbidden();
    $this->post("/finance/baskets/{$basket}/corrections", ['amount_cents' => 1, 'reason' => 'x', 'correction_id' => (string) Str::uuid7()])->assertForbidden();
    $this->post("/finance/baskets/{$basket}/collection-corrections", ['reason' => 'x', 'correction_id' => (string) Str::uuid7()])->assertForbidden();
    $this->put("/finance/baskets/{$basket}/collection")->assertForbidden();
    $this->post("/finance/households/{$household}/wallet/top-ups", ['amount' => '1'])->assertForbidden();
});

it('collects despite negative balance and no receipt, and reports draft or cancelled baskets as a domain error (R23b, R24)', function () {
    $manager = accountWith(Role::Manager);
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $draft = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $cancelled = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $this->actingAs($manager)->post("/finance/baskets/{$cancelled}/cancellation")->assertRedirect();
    $producer = ProducerModel::query()->create(['name' => 'Oil maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'stock_product_id' => $product,
        'type' => 'entry', 'quantity' => '2', 'occurred_at' => now(), 'recorded_by' => $manager->id]);
    $debt = app(CreateStockBasket::class)(Actor::manager($manager->id), $household);
    $this->post("/finance/baskets/{$debt}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$debt}/validation")->assertRedirect();

    app()->setLocale('ca');
    $this->actingAs(memberOf($household));
    $this->from("/baskets/{$draft}")->put("/baskets/{$draft}/collection")->assertSessionHasErrors(['domain' => 'Només es pot recollir una cistella validada.']);
    $this->from("/baskets/{$cancelled}")->put("/baskets/{$cancelled}/collection")->assertSessionHasErrors(['domain' => 'Només es pot recollir una cistella validada.']);
    $this->put("/baskets/{$debt}/collection")->assertRedirect()->assertSessionHasNoErrors();
    expect(DB::table('baskets')->where('id', $draft)->value('state'))->toBe('draft')
        ->and(DB::table('baskets')->where('id', $cancelled)->value('state'))->toBe('cancelled')
        ->and(DB::table('baskets')->where('id', $debt)->value('state'))->toBe('collected')
        ->and(DB::table('wallet_movements')->where('household_id', $household)->sum('amount_cents'))->toBeLessThan(0);
});

it('shows original collection, staff correction and new member collection append-only without ledger effects (R23b, R24, S26)', function () {
    $manager = accountWith(Role::Manager);
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $this->actingAs($manager);
    $basket = validatedReturnBasket($manager->id, $household, $item);
    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();
    $this->post("/finance/baskets/{$basket}/collection-corrections", ['reason' => 'Wrong mark', 'correction_id' => (string) Str::uuid7()])->assertRedirect();
    $ledger = [DB::table('wallet_movements')->get(), DB::table('stock_movements')->get()];

    $this->actingAs(memberOf($household))->put("/baskets/{$basket}/collection")->assertRedirect();
    $this->put("/baskets/{$basket}/collection")->assertRedirect();

    $this->get("/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'collected')
        ->where('basket.collectionEvents', fn ($events) => collect($events)->pluck('type')->all() === ['collected', 'corrected', 'collected']));
    expect([DB::table('wallet_movements')->get(), DB::table('stock_movements')->get()])->toEqual($ledger);
});
