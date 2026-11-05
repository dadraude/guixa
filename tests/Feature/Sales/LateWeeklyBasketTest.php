<?php

use Guixa\Sales\Application\ReadOpenHouseholdBaskets;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

it('revives the nothing-to-collect base and gives the validated household a late weekly basket, charged separately and gating closure (R18, R19, R24a, R24b, S30)', function () {
    $week = lateArrivalWeek();
    $reception = $week['reception'];
    expect(DB::table('baskets')->where('id', $week['baskets']['K'])->value('state'))->toBe('nothing_to_collect');
    $validatedBase = DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->put("{$reception}/products/{$week['cherries']}/received", ['received' => '2'])->assertRedirect();
    $this->post("{$reception}/validate")->assertRedirect();
    $this->post("{$reception}/distribution")->assertRedirect();

    $late = DB::table('baskets')->where('source_order_id', $week['orders']['A'])->where('late', true)->get();
    $lateLines = DB::table('basket_lines')->where('basket_id', $late[0]->id)->get();
    expect(DB::table('baskets')->where('id', $week['baskets']['K'])->value('state'))->toBe('draft')
        ->and((float) DB::table('basket_lines')->where('basket_id', $week['baskets']['K'])->value('quantity'))->toBe(1.0)
        ->and(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('validated')
        ->and(DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($validatedBase)
        ->and($late)->toHaveCount(1)
        ->and([$late[0]->kind, $late[0]->state, DB::table('orders')->where('id', $late[0]->source_order_id)->value('cycle_id'), $late[0]->validated_total_cents])->toBe(['weekly', 'draft', $week['cycle'], null])
        ->and($lateLines)->toHaveCount(1)
        ->and([$lateLines[0]->product_id, (float) $lateLines[0]->ordered_quantity, (float) $lateLines[0]->allocated_quantity, (float) $lateLines[0]->quantity])->toBe([$week['cherries'], 1.0, 1.0, 1.0])
        ->and(DB::table('wallet_movements')->count())->toBe(1)
        ->and(DB::table('basket_adjustments')->count())->toBe(0);

    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $this->post("/finance/baskets/{$week['baskets']['K']}/validation")->assertRedirect();
    expect(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->toBeNull();

    $this->post("/finance/baskets/{$late[0]->id}/validation")->assertRedirect();
    expect(DB::table('wallet_movements')->where('basket_id', $late[0]->id)->pluck('amount_cents')->all())->toBe([-500])
        ->and(DB::table('wallet_movements')->count())->toBe(4)
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->not->toBeNull();
});

it('adds a later late arrival to the household open late draft instead of a second basket (R18, R19, S91)', function () {
    $week = lateArrivalWeek();
    $reception = $week['reception'];

    foreach ([$week['cherries'], $week['eggs']] as $productId) {
        $this->delete("{$reception}/distribution")->assertRedirect();
        $this->put("{$reception}/products/{$productId}/received", ['received' => $productId === $week['cherries'] ? '2' : '1'])->assertRedirect();
        $this->post("{$reception}/validate")->assertRedirect();
        $this->post("{$reception}/distribution")->assertRedirect();
    }

    $late = DB::table('baskets')->where('source_order_id', $week['orders']['A'])->where('late', true)->get();
    expect($late)->toHaveCount(1)
        ->and(DB::table('baskets')->where('household_id', $week['households']['A'])->count())->toBe(2)
        ->and(DB::table('basket_lines')->where('basket_id', $late[0]->id)->orderBy('name')->pluck('quantity', 'product_id')->map(fn ($quantity) => (float) $quantity)->all())
        ->toBe([$week['cherries'] => 1.0, $week['eggs'] => 1.0])
        ->and(DB::table('wallet_movements')->count())->toBe(1);
});

it('refuses a second open late draft or a second base for one source order in the database (R19)', function () {
    $week = lateArrivalWeek();
    $row = fn (bool $late, string $state = 'draft') => [
        'id' => (string) Str::uuid7(), 'household_id' => $week['households']['A'], 'household_name' => 'Household A', 'kind' => 'weekly', 'state' => $state,
        'created_by' => null, 'created_at' => now(), 'source_order_id' => $week['orders']['A'], 'late' => $late,
    ];
    DB::table('baskets')->insert($row(true));

    expect(fn () => DB::transaction(fn () => DB::table('baskets')->insert($row(true))))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('baskets')->insert($row(false))))->toThrow(QueryException::class);
    DB::table('baskets')->where('late', true)->update(['state' => 'cancelled']);
    DB::table('baskets')->insert($row(true));
    expect(DB::table('baskets')->where('source_order_id', $week['orders']['A'])->where('late', true)->count())->toBe(2);
});

it('closes the cycle when the last draft is validated while validated baskets stay pending collection, keeps the indicator and rejects reopening (R23a, R24b, S54, S83)', function () {
    $week = lateArrivalWeek();
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();

    expect(DB::table('baskets')->where('id', $week['baskets']['K'])->value('state'))->toBe('nothing_to_collect')
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->not->toBeNull()
        ->and(DB::table('baskets')->whereIn('id', [$week['baskets']['A'], $week['baskets']['B']])->pluck('state')->all())->toBe(['validated', 'validated']);
    $open = app(ReadOpenHouseholdBaskets::class);
    expect(array_map(fn ($basket) => [$basket->basketId, $basket->status], $open($week['households']['A'])))->toBe([[$week['baskets']['A'], 'validated']])
        ->and($open($week['households']['K']))->toBe([]);

    $this->delete("{$week['reception']}/distribution")->assertSessionHasErrors('domain');
    $this->post("/finance/baskets/{$week['baskets']['A']}/corrections", [
        'amount_cents' => -100, 'reason' => 'Damaged loaf', 'correction_id' => (string) Str::uuid7(),
    ])->assertSessionHasNoErrors();
    expect(DB::table('wallet_movements')->count())->toBe(3);
});

it('keeps a validated basket pending and charged once after the planned pickup day passes, and lets it be collected later (R24, R24b, S42)', function () {
    $week = lateArrivalWeek();
    $this->travelTo(now()->addDays(45));
    Artisan::call('schedule:run');

    expect(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('validated')
        ->and(DB::table('wallet_movements')->where('basket_id', $week['baskets']['A'])->pluck('amount_cents')->all())->toBe([-700])
        ->and(DB::table('stock_movements')->count())->toBe(0);

    $this->put("/finance/baskets/{$week['baskets']['A']}/collection")->assertRedirect();
    expect(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('collected')
        ->and(DB::table('wallet_movements')->count())->toBe(1);
});

it('lists the late basket as its own marked row after the base and sends the old pickup URL to the base basket (R19, R24)', function () {
    $week = lateArrivalWeek();
    $reception = $week['reception'];
    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->put("{$reception}/products/{$week['cherries']}/received", ['received' => '2'])->assertRedirect();
    $this->post("{$reception}/validate")->assertRedirect();
    $this->post("{$reception}/distribution")->assertRedirect();
    $late = (string) DB::table('baskets')->where('source_order_id', $week['orders']['A'])->where('late', true)->value('id');

    $this->get("/finance/baskets/{$late}")->assertInertia(fn (AssertableInertia $page) => $page->where('basket.late', true));
    $this->get("/finance/baskets/{$week['baskets']['A']}")->assertInertia(fn (AssertableInertia $page) => $page->where('basket.late', false));
    $this->get("/manage/cycles/{$week['cycle']}/pickups/{$week['households']['A']}")->assertRedirect("/finance/baskets/{$week['baskets']['A']}");
    $this->get("/manage/cycles/{$week['cycle']}/pickups")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pickups', fn ($rows) => collect($rows)->where('householdId', $week['households']['A'])->map(fn ($row) => [$row['basketId'], $row['late']])->values()->all()
            === [[$week['baskets']['A'], false], [$late, true]]));
});

it('gives the validated household a late weekly basket when an unordered product is added after the reopen and allocated to it by hand (R14, R16, R18, R19, S40)', function () {
    $week = lateArrivalWeek();
    $reception = $week['reception'];
    $producer = DB::table('products')->where('id', $week['cherries'])->value('producer_id');
    $plums = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $plums, 'producer_id' => $producer, 'name' => 'Plums', 'unit' => 'unit', 'price_cents' => 400, 'variable' => false, 'active' => true]);
    $base = DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->post("{$reception}/products", ['product_id' => $plums, 'received' => '1'])->assertRedirect();
    $this->put("{$reception}/products/{$plums}/allocations/{$week['households']['A']}", ['quantity' => '1'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post("{$reception}/validate")->assertRedirect();
    $this->post("{$reception}/distribution")->assertRedirect();

    $late = DB::table('baskets')->where('source_order_id', $week['orders']['A'])->where('late', true)->get();
    expect($late)->toHaveCount(1)
        ->and([$late[0]->state, $late[0]->validated_total_cents])->toBe(['draft', null])
        ->and(DB::table('basket_lines')->where('basket_id', $late[0]->id)->get()->map(fn ($line) => [$line->product_id, (float) $line->quantity, $line->price_cents])->all())->toBe([[$plums, 1.0, 400]])
        ->and(DB::table('baskets')->where('id', $week['baskets']['A'])->value('state'))->toBe('validated')
        ->and(DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($base)
        ->and(DB::table('wallet_movements')->count())->toBe(1);

    $this->post("/finance/baskets/{$late[0]->id}/validation")->assertRedirect();
    expect(DB::table('wallet_movements')->where('basket_id', $late[0]->id)->pluck('amount_cents')->all())->toBe([-400]);
});

it('splits an unordered product between two fully validated households into two late drafts and corrects each, including to zero, with the ledger unchanged until validation (R14, R16, R18, R19, S40, S91)', function () {
    $week = lateArrivalWeek(withSpareDraft: true);
    $reception = $week['reception'];
    $producer = DB::table('products')->where('id', $week['cherries'])->value('producer_id');
    $plums = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $plums, 'producer_id' => $producer, 'name' => 'Plums', 'unit' => 'unit', 'price_cents' => 400, 'variable' => false, 'active' => true]);
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $bases = fn () => DB::table('baskets')->whereIn('id', [$week['baskets']['A'], $week['baskets']['B']])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()
        + [DB::table('basket_lines')->whereIn('basket_id', [$week['baskets']['A'], $week['baskets']['B']])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
    $before = $bases();
    $ledger = DB::table('wallet_movements')->count();
    $allocate = fn (string $household, string $quantity) => $this->put("{$reception}/products/{$plums}/allocations/{$week['households'][$household]}", ['quantity' => $quantity])->assertRedirect()->assertSessionHasNoErrors();

    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->post("{$reception}/products", ['product_id' => $plums, 'received' => '2'])->assertRedirect();
    $allocate('A', '1');
    $allocate('B', '1');
    expect(DB::table('baskets')->where('late', true)->count())->toBe(2);
    $allocate('A', '2');
    $allocate('B', '0');

    $lateOf = fn (string $household) => DB::table('baskets')->where('source_order_id', $week['orders'][$household])->where('late', true)->first();
    $linesOf = fn (string $basket) => DB::table('basket_lines')->where('basket_id', $basket)->get()->map(fn ($line) => [$line->product_id, (float) $line->quantity, (float) $line->allocated_quantity])->all();
    expect($lateOf('A')->state)->toBe('draft')
        ->and($linesOf($lateOf('A')->id))->toBe([[$plums, 2.0, 2.0]])
        ->and($lateOf('B')->state)->toBe('nothing_to_collect')
        ->and($linesOf($lateOf('B')->id))->toBe([[$plums, 0.0, 0.0]])
        ->and($bases())->toBe($before)
        ->and(DB::table('wallet_movements')->count())->toBe($ledger);

    $this->post("{$reception}/validate")->assertRedirect();
    $this->post("{$reception}/distribution")->assertRedirect();
    expect(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->toBeNull();
    $this->post("/finance/baskets/{$week['baskets']['C']}/validation")->assertRedirect();
    expect(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->toBeNull();
    $this->post("/finance/baskets/{$lateOf('A')->id}/validation")->assertRedirect();
    expect(DB::table('wallet_movements')->where('basket_id', $lateOf('A')->id)->pluck('amount_cents')->all())->toBe([-800])
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->not->toBeNull();
});

it('rejects a hand allocation of a product a validated basket has a line of, even at zero, over HTTP and changes nothing (R16, R18, R23)', function () {
    $week = lateArrivalWeek();
    $reception = $week['reception'];
    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->put("{$reception}/products/{$week['cherries']}/received", ['received' => '2'])->assertRedirect();
    $before = [DB::table('order_lines')->orderBy('id')->pluck('allocated_quantity', 'id')->all(), DB::table('baskets')->count(), DB::table('wallet_movements')->count()];

    $this->put("{$reception}/products/{$week['cherries']}/allocations/{$week['households']['B']}", ['quantity' => '1'])
        ->assertSessionHasErrors(['domain' => __('errors.RedistributionBlockedByValidatedBasket')]);

    expect([DB::table('order_lines')->orderBy('id')->pluck('allocated_quantity', 'id')->all(), DB::table('baskets')->count(), DB::table('wallet_movements')->count()])->toBe($before);
});
