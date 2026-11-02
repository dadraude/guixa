<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\CorrectBasket;
use Guixa\Sales\Application\GetBasket;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('replays a persisted amount correction and shows original and effective totals to staff and the household member (R23, R31, R32)', function () {
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
    $manager = accountWith(Role::Manager);
    $member = memberOf($household);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Jar', 50);
    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $this->post("/finance/baskets/{$basket}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    $identity = (string) Str::uuid7();

    $this->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => -20, 'reason' => 'Adjusted jar credit', 'correction_id' => $identity,
    ])->assertRedirect();
    app(CorrectBasket::class)(Actor::manager($manager->id), $basket, -20, 'Adjusted jar credit', $identity);

    expect(DB::table('basket_adjustments')->where('basket_id', $basket)->count())->toBe(1)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->where('type', 'correction')->count())->toBe(1)
        ->and(DB::table('baskets')->where('id', $basket)->value('validated_total_cents'))->toBe(-50)
        ->and(app(GetBasket::class)(Actor::manager($manager->id), $basket)->effectiveTotalCents)->toBe(-30);
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.totalCents', -50)->where('basket.effectiveTotalCents', -30)
        ->where('basket.adjustments.0.reason', 'Adjusted jar credit'));
    $this->actingAs($member)->get("/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.totalCents', -50)->where('basket.effectiveTotalCents', -30)
        ->where('basket.adjustments.0.id', $identity));

    $original = DB::table('baskets')->where('id', $basket)->first();
    $adjustment = DB::table('basket_adjustments')->where('id', $identity)->first();
    $stockCount = DB::table('stock_movements')->where('basket_id', $basket)->count();
    $walletCount = DB::table('wallet_movements')->where('basket_id', $basket)->count();
    app(MarkBasketCollected::class)(Actor::manager($manager->id), $basket);
    app(MarkBasketCollected::class)(Actor::manager($manager->id), $basket);
    $collected = DB::table('baskets')->where('id', $basket)->first();
    expect($collected->state)->toBe('collected')
        ->and($collected->collected_by)->toBe($manager->id)
        ->and($collected->collected_at)->not->toBeNull()
        ->and($collected->validated_at)->toBe($original->validated_at)
        ->and($collected->validated_total_cents)->toBe($original->validated_total_cents)
        ->and(DB::table('basket_adjustments')->where('id', $identity)->first())->toEqual($adjustment)
        ->and(app(GetBasket::class)(Actor::manager($manager->id), $basket)->effectiveTotalCents)->toBe(-30)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->count())->toBe($stockCount)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->count())->toBe($walletCount);

    $this->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => 10, 'reason' => 'Forbidden', 'correction_id' => (string) Str::uuid7(),
    ])->assertForbidden();
    $otherHousehold = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $otherHousehold, 'name' => 'Other', 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs(memberOf($otherHousehold))->get("/baskets/{$basket}")->assertNotFound();
    $this->actingAs($manager)->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => 0, 'reason' => 'Invalid', 'correction_id' => (string) Str::uuid7(),
    ])->assertSessionHasErrors('amount_cents');
    $this->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => 10, 'reason' => '', 'correction_id' => (string) Str::uuid7(),
    ])->assertSessionHasErrors('reason');
    $this->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => 10, 'reason' => 'Changed', 'correction_id' => $identity,
    ])->assertSessionHasErrors('domain');
    expect(DB::table('basket_adjustments')->where('basket_id', $basket)->count())->toBe(1);

    $finance = accountWith(Role::Finance);
    $secondIdentity = (string) Str::uuid7();
    $this->actingAs($finance)->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => 10, 'reason' => 'Second correction', 'correction_id' => $secondIdentity,
    ])->assertRedirect();
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.adjustments.0.id', $identity)->where('basket.adjustments.1.id', $secondIdentity)
        ->where('basket.effectiveTotalCents', -40));

    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $otherBasket = DB::table('baskets')->where('id', '!=', $basket)->value('id');
    $this->post("/finance/baskets/{$otherBasket}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$otherBasket}/validation")->assertRedirect();
    $this->post("/finance/baskets/{$otherBasket}/corrections", [
        'amount_cents' => -20, 'reason' => 'Adjusted jar credit', 'correction_id' => $identity,
    ])->assertSessionHasErrors('domain');
    expect(DB::table('basket_adjustments')->where('basket_id', $otherBasket)->count())->toBe(0);

    app()->instance(WalletLedger::class, new class implements WalletLedger
    {
        public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void {}

        public function balance(Actor $actor, string $householdId): int
        {
            return 0;
        }

        public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
        {
            throw new RuntimeException('Wallet unavailable');
        }
    });
    expect(fn () => app(CorrectBasket::class)(Actor::manager($manager->id), $basket, 10, 'Failed correction', (string) Str::uuid7()))
        ->toThrow(RuntimeException::class, 'Wallet unavailable');
    expect(DB::table('basket_adjustments')->where('basket_id', $basket)->count())->toBe(2);
});

it('corrects a validated net-zero basket through its basket source without a base wallet movement (R23, R32)', function () {
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
    $manager = accountWith(Role::Manager);
    $producer = ProducerModel::query()->create(['name' => 'Maker', 'active' => true,
        'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 50, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'stock_product_id' => $product,
        'type' => 'entry', 'quantity' => '1', 'occurred_at' => now(), 'recorded_by' => $manager->id]);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Jar', 50);
    $this->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $this->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/return-lines", ['return_item_id' => $item, 'return_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    expect(DB::table('baskets')->where('id', $basket)->value('validated_total_cents'))->toBe(0)
        ->and(DB::table('wallet_movements')->where('basket_id', $basket)->count())->toBe(0);

    $identity = (string) Str::uuid7();
    $this->post("/finance/baskets/{$basket}/corrections", [
        'amount_cents' => 25, 'reason' => 'Corrected amount', 'correction_id' => $identity,
    ])->assertRedirect();
    expect(DB::table('wallet_movements')->where('id', $identity)->value('basket_id'))->toBe($basket)
        ->and(DB::table('wallet_movements')->where('id', $identity)->value('source_movement_id'))->toBeNull()
        ->and(DB::table('basket_adjustments')->where('id', $identity)->value('basket_id'))->toBe($basket)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->count())->toBe(1)
        ->and(app(GetBasket::class)(Actor::manager($manager->id), $basket)->effectiveTotalCents)->toBe(-25);
});
