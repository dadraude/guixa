<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\GetBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FrozenClock;

it('retains the original collection and renders a corrected pending state without ledger effects (R23b, R24a)', function () {
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
    $manager = accountWith(Role::Manager);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Jar', 50);
    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $this->post("/finance/baskets/{$basket}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();

    $original = DB::table('baskets')->where('id', $basket)->first();
    $wallet = DB::table('wallet_movements')->where('basket_id', $basket)->get();
    $stockCount = DB::table('stock_movements')->where('basket_id', $basket)->count();
    $correction = (string) Str::uuid7();

    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correction, 'reason' => 'Marked by mistake',
    ])->assertRedirect();

    $events = DB::table('basket_collection_events')->where('basket_id', $basket)->orderBy('occurred_at')->get();
    expect($events)->toHaveCount(2)
        ->and($events[0]->type)->toBe('collected')
        ->and($events[0]->recorded_by)->toBe($manager->id)
        ->and($events[1]->reason)->toBe('Marked by mistake')
        ->and(DB::table('baskets')->where('id', $basket)->value('collected_at'))->toBe($original->collected_at)
        ->and(DB::table('baskets')->where('id', $basket)->value('validated_at'))->toBe($original->validated_at)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->count())->toBe($stockCount)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->get())->toEqual($wallet)
        ->and(app(GetBasket::class)(Actor::manager($manager->id), $basket)->state)->toBe('validated');
    $this->get("/finance/baskets/{$basket}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'validated')->where('basket.collectionEvents.0.type', 'collected')
        ->where('basket.collectionEvents.1.reason', 'Marked by mistake'));
});

it('allows Finance to correct a stock basket and shows history only to its household member', function () {
    $household = (string) Str::uuid7();
    $otherHousehold = (string) Str::uuid7();
    foreach ([$household, $otherHousehold] as $id) {
        DB::table('households')->insert(['id' => $id, 'name' => 'Household', 'created_at' => now(), 'updated_at' => now()]);
    }
    $manager = accountWith(Role::Manager);
    $finance = accountWith(Role::Finance);
    $member = memberOf($household);
    $otherMember = memberOf($otherHousehold);
    $producer = ProducerModel::query()->create(['name' => 'Maker', 'active' => true,
        'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id,
        'stock' => true, 'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200,
        'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $product,
        'type' => 'entry', 'quantity' => '2', 'occurred_at' => now(), 'recorded_by' => $manager->id]);

    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $this->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();
    $stock = DB::table('stock_movements')->where('basket_id', $basket)->get();
    $wallet = DB::table('wallet_movements')->where('basket_id', $basket)->get();
    $original = DB::table('baskets')->where('id', $basket)->first();

    $correction = (string) Str::uuid7();
    $this->actingAs($finance)->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correction, 'reason' => 'Wrong basket',
    ])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correction, 'reason' => 'Wrong basket',
    ])->assertRedirect();
    expect(DB::table('basket_collection_events')->where('basket_id', $basket)->count())->toBe(2)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->get())->toEqual($stock)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->get())->toEqual($wallet)
        ->and(DB::table('baskets')->where('id', $basket)->value('validated_total_cents'))->toBe($original->validated_total_cents);
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correction, 'reason' => 'Different',
    ])->assertSessionHasErrors('domain');
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => (string) Str::uuid7(), 'reason' => '',
    ])->assertSessionHasErrors('reason');
    $this->actingAs($member)->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => (string) Str::uuid7(), 'reason' => 'Denied',
    ])->assertForbidden();
    $this->get("/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'validated')->where('basket.collectionEvents.0.type', 'collected')
        ->where('basket.collectionEvents.1.reason', 'Wrong basket'));
    $this->actingAs($otherMember)->get("/baskets/{$basket}")->assertNotFound();
    $this->actingAs($finance)->put("/finance/baskets/{$basket}/collection")->assertRedirect();
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'collected')->has('basket.collectionEvents', 3));
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correction, 'reason' => 'Wrong basket',
    ])->assertRedirect();
    expect(DB::table('basket_collection_events')->where('basket_id', $basket)->count())->toBe(3)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->get())->toEqual($stock)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->get())->toEqual($wallet);
});

it('preserves causal collection order when all events share one stored second and a correction has a high UUID', function () {
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
    $manager = accountWith(Role::Manager);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Jar', 50);
    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $this->post("/finance/baskets/{$basket}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    app()->instance(Clock::class, new FrozenClock('2026-11-01T10:00:00+00:00'));
    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();
    $original = DB::table('baskets')->where('id', $basket)->first();
    $wallet = DB::table('wallet_movements')->where('basket_id', $basket)->get();
    $stock = DB::table('stock_movements')->where('basket_id', $basket)->get();
    $correctionId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correctionId, 'reason' => 'Wrong mark',
    ])->assertRedirect();
    $this->put("/finance/baskets/{$basket}/collection")->assertRedirect();

    $history = app(GetBasket::class)(Actor::manager($manager->id), $basket)->collectionEvents;
    expect(array_map(fn ($event) => $event->type, $history))->toBe(['collected', 'corrected', 'collected'])
        ->and($history[1]->id)->toBe($correctionId)
        ->and(DB::table('basket_collection_events')->where('basket_id', $basket)->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and(DB::table('baskets')->where('id', $basket)->value('state'))->toBe('collected')
        ->and(DB::table('baskets')->where('id', $basket)->value('collected_by'))->toBe($original->collected_by)
        ->and(DB::table('baskets')->where('id', $basket)->value('collected_at'))->toBe($original->collected_at)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->get())->toEqual($wallet)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->get())->toEqual($stock);
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.state', 'collected')
        ->where('basket.collectionEvents.0.type', 'collected')
        ->where('basket.collectionEvents.1.type', 'corrected')
        ->where('basket.collectionEvents.2.type', 'collected'));
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => $correctionId, 'reason' => 'Wrong mark',
    ])->assertRedirect();
    expect(DB::table('baskets')->where('id', $basket)->value('state'))->toBe('collected')
        ->and(DB::table('basket_collection_events')->where('basket_id', $basket)->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2, 3]);
    $this->post("/finance/baskets/{$basket}/collection-corrections", [
        'correction_id' => (string) Str::uuid7(), 'reason' => 'Second wrong mark',
    ])->assertRedirect();
    expect(array_map(fn ($event) => $event->type,
        app(GetBasket::class)(Actor::manager($manager->id), $basket)->collectionEvents))
        ->toBe(['collected', 'corrected', 'collected', 'corrected'])
        ->and(DB::table('basket_collection_events')->where('basket_id', $basket)->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2, 3, 4]);
});
