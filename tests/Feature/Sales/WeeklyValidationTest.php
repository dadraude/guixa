<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Application\WeeklyBasketSource;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Household A holds a weekly base of 4 loaves at EUR 5 and a draft with 2 of 5 jars of honey at EUR 6.
 *
 * @return array{week: array<string, mixed>, honey: string}
 */
function weeklyWithHoney(): array
{
    $week = twoHouseholdWeek(500);
    $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $honey = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $honey, 'producer_id' => $producer->id, 'stock' => true, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 600, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $honey, 'type' => 'entry', 'quantity' => '5', 'occurred_at' => now(), 'recorded_by' => $week['manager']->id]);
    test()->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => '2'])->assertRedirect();

    return ['week' => $week, 'honey' => $honey];
}

/**
 * The rows a failed validation must leave exactly as they were.
 *
 * @return list<mixed>
 */
function validationState(string $cycle): array
{
    return [
        DB::table('baskets')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        DB::table('wallet_movements')->count(),
        DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        DB::table('cycles')->where('id', $cycle)->value('closed_at'),
    ];
}

it('keeps a stock line through a reception sync and charges weekly plus stock once, leaving 3 jars (R20, R22, R28, S16)', function () {
    ['week' => $week, 'honey' => $honey] = weeklyWithHoney();
    $reception = "/receptions/{$week['cycle']}/{$week['producer']}";

    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->put("{$reception}/products/{$week['loaf']}/received", ['received' => '5'])->assertRedirect();
    $this->post("{$reception}/validate")->assertRedirect();
    $this->post("{$reception}/distribution")->assertRedirect();

    expect(DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->where('kind', 'stock')->pluck('quantity')->map(fn ($q) => (float) $q)->all())->toBe([2.0]);
    $this->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect()->assertSessionHasNoErrors();

    $sale = DB::table('stock_movements')->where('product_id', $honey)->where('basket_id', $week['baskets']['A'])->get();
    expect(DB::table('wallet_movements')->where('basket_id', $week['baskets']['A'])->pluck('amount_cents')->all())->toBe([-3200])
        ->and(DB::table('baskets')->where('id', $week['baskets']['A'])->value('validated_total_cents'))->toBe(3200)
        ->and($sale)->toHaveCount(1)
        ->and((float) $sale[0]->quantity)->toBe(-2.0)
        ->and((float) DB::table('stock_movements')->where('product_id', $honey)->sum('quantity'))->toBe(3.0);

    $movements = [DB::table('wallet_movements')->count(), DB::table('stock_movements')->count()];
    $this->put("/finance/baskets/{$week['baskets']['A']}/collection")->assertRedirect();
    expect(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('collected')
        ->and([DB::table('wallet_movements')->count(), DB::table('stock_movements')->count()])->toBe($movements);
});

it('rolls a weekly validation back whole when the Wallet or the closure fails after stock was sold, and a retry charges once (R22, R24b)', function (string $failing) {
    ['week' => $week] = weeklyWithHoney();
    // B is validated first, so validating A is the last step and closes the cycle.
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $basket = $week['baskets']['A'];
    $before = validationState($week['cycle']);
    $ledger = app(WalletLedger::class);
    $source = app(WeeklyBasketSource::class);
    $this->app->bind(WalletLedger::class, fn () => new class($ledger, $failing === 'wallet') implements WalletLedger
    {
        public function __construct(private WalletLedger $real, private bool $fail) {}

        public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
        {
            $this->real->charge($actor, $householdId, $basketId, $totalCents);
            if ($this->fail) {
                throw new RuntimeException('Wallet failed after the charge');
            }
        }

        public function balance(Actor $actor, string $householdId): int
        {
            return $this->real->balance($actor, $householdId);
        }

        public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
        {
            $this->real->correct($actor, $householdId, $basketId, $amountCents, $reason, $correctionId);
        }
    });
    $this->app->bind(WeeklyBasketSource::class, fn () => new class($source, $failing === 'closure') implements WeeklyBasketSource
    {
        public function __construct(private WeeklyBasketSource $real, private bool $fail) {}

        public function lockSettled(string $cycleId): void
        {
            $this->real->lockSettled($cycleId);
        }

        public function closeIfFinished(string $cycleId): void
        {
            // The real closure writes closed_at; the failure comes after it, so the rollback has something to undo.
            $this->real->closeIfFinished($cycleId);
            if ($this->fail) {
                throw new RuntimeException('Closure failed');
            }
        }

        public function cyclesOf(array $sourceOrderIds): array
        {
            return $this->real->cyclesOf($sourceOrderIds);
        }

        public function ofCycle(string $cycleId): array
        {
            return $this->real->ofCycle($cycleId);
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($week['manager'])->post("/finance/baskets/{$basket}/validation"))->toThrow(RuntimeException::class);
    expect(validationState($week['cycle']))->toBe($before)
        ->and(DB::table('baskets')->where('id', $basket)->value('state'))->toBe('draft');

    $this->app->forgetInstance(WalletLedger::class);
    $this->app->bind(WalletLedger::class, fn () => $ledger);
    $this->app->bind(WeeklyBasketSource::class, fn () => $source);
    $this->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    expect(DB::table('wallet_movements')->where('basket_id', $basket)->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('basket_id', $basket)->count())->toBe(1)
        ->and(DB::table('baskets')->where('id', $basket)->value('state'))->toBe('validated')
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->not->toBeNull();
})->with(['wallet', 'closure']);
