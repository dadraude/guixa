<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\CorrectBasket;
use Guixa\Sales\Application\GetBasket;
use Guixa\Sales\Application\StockCorrector;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Shared\Domain\UtcTimestamp;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function validatedStockBasket(object $test, string $stockQuantity = '5', string $quantity = '3'): array
{
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
    $manager = accountWith(Role::Manager);
    $producer = ProducerModel::query()->create(['name' => 'Maker', 'active' => true,
        'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => 1200, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $product,
        'type' => 'entry', 'quantity' => $stockQuantity, 'occurred_at' => UtcTimestamp::toStorage(now()), 'recorded_by' => $manager->id]);
    $test->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = DB::table('baskets')->value('id');
    $test->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => $quantity])->assertRedirect();
    $test->post("/finance/baskets/{$basket}/validation")->assertRedirect();

    return [$manager, $household, $basket, $product];
}

it('persists returned goods with linked stock and wallet corrections and shows original and effective values (S86, R23, R27, R28, R32)', function () {
    [$manager, $household, $basket, $product] = validatedStockBasket($this);
    $original = DB::table('baskets')->where('id', $basket)->first();
    $sale = DB::table('stock_movements')->where('basket_id', $basket)->where('type', 'sale')->first();
    $charge = DB::table('wallet_movements')->where('basket_id', $basket)->where('type', 'charge')->first();
    $identity = (string) Str::uuid7();
    $payload = ['amount_cents' => 1200, 'reason' => 'One bottle came back', 'correction_id' => $identity,
        'returned_goods' => [['stock_product_id' => $product, 'quantity' => '1']]];

    $this->post("/finance/baskets/{$basket}/corrections", $payload)->assertRedirect();
    $this->post("/finance/baskets/{$basket}/corrections", $payload)->assertRedirect();

    $movement = DB::table('stock_movements')->where('correction_id', $identity)->first();
    expect($movement->type)->toBe('adjustment')
        ->and($movement->basket_id)->toBe($basket)
        ->and((float) $movement->quantity)->toBe(1.0)
        ->and($movement->note)->toBe('One bottle came back')
        ->and($movement->recorded_by)->toBe($manager->id)
        ->and($movement->occurred_at)->not->toBeNull()
        ->and(DB::table('stock_movements')->where('correction_id', $identity)->count())->toBe(1)
        ->and(DB::table('basket_adjustments')->where('id', $identity)->count())->toBe(1)
        ->and((float) DB::table('basket_adjustment_lines')->where('adjustment_id', $identity)->value('quantity_delta'))->toBe(-1.0)
        ->and(DB::table('wallet_movements')->where('id', $identity)->value('amount_cents'))->toBe(1200)
        ->and(DB::table('baskets')->where('id', $basket)->first())->toEqual($original)
        ->and(DB::table('stock_movements')->where('id', $sale->id)->first())->toEqual($sale)
        ->and(DB::table('wallet_movements')->where('id', $charge->id)->first())->toEqual($charge)
        ->and((float) DB::table('stock_movements')->where('product_id', $product)->sum('quantity'))->toBe(3.0);

    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.totalCents', 3600)->where('basket.effectiveTotalCents', 2400)
        ->where('basket.stockLines.0.quantity', '3.000')->where('basket.stockLines.0.effectiveQuantity', '2.000')
        ->where('basket.stockLines.0.amountCents', 3600)->where('basket.stockLines.0.effectiveAmountCents', 2400)
        ->where('basket.adjustments.0.lines.0.stockProductId', $product)
        ->where('basket.adjustments.0.lines.0.quantityDelta', '-1.000'));
    $this->actingAs(memberOf($household))->get("/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.stockLines.0.effectiveQuantity', '2.000')->where('basket.effectiveTotalCents', 2400));
});

it('rolls back every effect when the wallet fails after the stock write (S86, R23)', function () {
    [$manager, , $basket, $product] = validatedStockBasket($this);
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

    expect(fn () => app(CorrectBasket::class)(Actor::manager($manager->id), $basket, 1200, 'Came back', (string) Str::uuid7(), [$product => '1']))
        ->toThrow(RuntimeException::class, 'Wallet unavailable');

    expect(DB::table('basket_adjustments')->where('basket_id', $basket)->count())->toBe(0)
        ->and(DB::table('basket_adjustment_lines')->count())->toBe(0)
        ->and(DB::table('stock_movements')->whereNotNull('correction_id')->count())->toBe(0)
        ->and((float) DB::table('stock_movements')->where('product_id', $product)->sum('quantity'))->toBe(2.0)
        ->and(app(GetBasket::class)(Actor::manager($manager->id), $basket)->stockLines[0]->effectiveQuantity)->toBe('3.000');
});

it('rolls back the first stock write when a later stock correction fails (S86, R23)', function () {
    [$manager, $household, $basket, $product] = validatedStockBasket($this);
    $producer = DB::table('products')->where('id', $product)->value('producer_id');
    $second = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $second, 'producer_id' => $producer, 'stock' => true,
        'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 500, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $second,
        'type' => 'entry', 'quantity' => '5', 'occurred_at' => UtcTimestamp::toStorage(now()), 'recorded_by' => $manager->id]);
    $real = app(StockCorrector::class);
    app()->instance(StockCorrector::class, new class($real) implements StockCorrector
    {
        private int $calls = 0;

        public function __construct(private StockCorrector $real) {}

        public function correct(Actor $actor, string $productId, string $quantity, string $reason, string $basketId, string $correctionId): void
        {
            if (++$this->calls === 2) {
                throw new RuntimeException('Stock unavailable');
            }
            $this->real->correct($actor, $productId, $quantity, $reason, $basketId, $correctionId);
        }
    });
    $this->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $other = DB::table('baskets')->where('id', '!=', $basket)->value('id');
    $this->post("/finance/baskets/{$other}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$other}/stock-lines", ['stock_product_id' => $second, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$other}/validation")->assertRedirect();
    $before = (float) DB::table('stock_movements')->whereIn('product_id', [$product, $second])->sum('quantity');

    expect(fn () => app(CorrectBasket::class)(Actor::manager($manager->id), $other, 1700, 'Both came back',
        (string) Str::uuid7(), [$product => '1', $second => '1']))->toThrow(RuntimeException::class, 'Stock unavailable');

    expect(DB::table('basket_adjustments')->where('basket_id', $other)->count())->toBe(0)
        ->and(DB::table('stock_movements')->whereNotNull('correction_id')->count())->toBe(0)
        ->and((float) DB::table('stock_movements')->whereIn('product_id', [$product, $second])->sum('quantity'))->toBe($before);
});

it('shows a fully reversed basket as not pending and keeps the original contents (R24a, R23)', function () {
    [$manager, $household, $basket, $product] = validatedStockBasket($this);

    $this->post("/finance/baskets/{$basket}/corrections", ['amount_cents' => 3600, 'reason' => 'All back',
        'correction_id' => (string) Str::uuid7(), 'returned_goods' => [['stock_product_id' => $product, 'quantity' => '3']]])->assertRedirect();

    expect((float) DB::table('stock_movements')->where('product_id', $product)->sum('quantity'))->toBe(5.0)
        ->and(DB::table('basket_lines')->where('basket_id', $basket)->value('quantity'))->toBe('3.000');
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.pendingCollection', false)->where('basket.state', 'validated')
        ->where('basket.stockLines.0.effectiveQuantity', '0.000')->where('basket.effectiveTotalCents', 0));
    $this->actingAs(memberOf($household))->get("/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.pendingCollection', false)->where('basket.stockLines.0.quantity', '3.000'));
    $this->get('/baskets')->assertInertia(fn (Assert $page) => $page->where('baskets.0.pendingCollection', false));
});

it('rejects invalid goods input through the form request and the domain without any effect (R23)', function () {
    [$manager, , $basket, $product] = validatedStockBasket($this);
    $base = ['amount_cents' => 1200, 'reason' => 'Came back', 'correction_id' => (string) Str::uuid7()];

    $this->post("/finance/baskets/{$basket}/corrections", [...$base, 'reason' => '',
        'returned_goods' => [['stock_product_id' => $product, 'quantity' => '1']]])->assertSessionHasErrors('reason');
    $this->post("/finance/baskets/{$basket}/corrections", [...$base,
        'returned_goods' => [['stock_product_id' => $product, 'quantity' => '4']]])->assertSessionHasErrors('domain');
    $this->post("/finance/baskets/{$basket}/corrections", [...$base,
        'returned_goods' => [['stock_product_id' => (string) Str::uuid7(), 'quantity' => '1']]])->assertSessionHasErrors('domain');
    $this->post("/finance/baskets/{$basket}/corrections", [...$base,
        'returned_goods' => [['stock_product_id' => 'not-a-uuid', 'quantity' => '1']]])->assertSessionHasErrors('returned_goods.0.stock_product_id');

    expect(DB::table('basket_adjustments')->count())->toBe(0)
        ->and(DB::table('stock_movements')->whereNotNull('correction_id')->count())->toBe(0);
});

it('does not give Finance an arbitrary inventory adjustment (R27)', function () {
    [, , , $product] = validatedStockBasket($this);

    $this->actingAs(accountWith(Role::Finance))->post("/manage/stock/{$product}/adjustments", ['quantity' => '1', 'note' => 'count'])
        ->assertForbidden();

    expect(DB::table('stock_movements')->where('type', 'adjustment')->count())->toBe(0);
});

it('replays a full reversal and a partial return over HTTP with exactly one effect on every table (R32)', function (string $returned, string $effective) {
    [, , $basket, $product] = validatedStockBasket($this);
    $identity = (string) Str::uuid7();
    $payload = ['amount_cents' => 1200, 'reason' => 'Came back', 'correction_id' => $identity,
        'returned_goods' => [['stock_product_id' => $product, 'quantity' => $returned]]];

    $this->post("/finance/baskets/{$basket}/corrections", $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post("/finance/baskets/{$basket}/corrections", $payload)->assertRedirect()->assertSessionHasNoErrors();

    expect(DB::table('basket_adjustments')->where('basket_id', $basket)->count())->toBe(1)
        ->and(DB::table('basket_adjustment_lines')->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('correction_id', $identity)->count())->toBe(1)
        ->and(DB::table('wallet_movements')->where('id', $identity)->count())->toBe(1);
    $this->get("/finance/baskets/{$basket}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.stockLines.0.effectiveQuantity', $effective));
    $this->post("/finance/baskets/{$basket}/corrections", [...$payload, 'amount_cents' => 1300])->assertSessionHasErrors('domain');
    expect(DB::table('basket_adjustments')->count())->toBe(1);
})->with([
    'full reversal' => ['3', '0.000'],
    'two of three' => ['2', '1.000'],
]);

it('keeps a stock and return basket pending after its stock is fully reversed (R24a, S88)', function () {
    [$manager, $household, $basket, $product] = validatedStockBasket($this);
    $item = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Jar', 50);
    $this->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $mixed = DB::table('baskets')->where('id', '!=', $basket)->value('id');
    $this->post("/finance/baskets/{$mixed}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => '1'])->assertRedirect();
    $this->post("/finance/baskets/{$mixed}/return-lines", ['return_item_id' => $item, 'return_quantity' => '2'])->assertRedirect();
    $this->post("/finance/baskets/{$mixed}/validation")->assertRedirect();

    $this->post("/finance/baskets/{$mixed}/corrections", ['amount_cents' => 1200, 'reason' => 'Bottle came back',
        'correction_id' => (string) Str::uuid7(), 'returned_goods' => [['stock_product_id' => $product, 'quantity' => '1']]])->assertRedirect();

    $this->get("/finance/baskets/{$mixed}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.stockLines.0.effectiveQuantity', '0.000')->where('basket.pendingCollection', true));
    $this->actingAs(memberOf($household))->get("/baskets/{$mixed}")->assertInertia(fn (Assert $page) => $page
        ->where('basket.pendingCollection', true));
});
