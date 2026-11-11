<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/** A closed cycle where A's and B's baskets are validated and the cycle has closed. */
function closedWeek(object $test): array
{
    $week = lateArrivalWeek(withSpareDraft: false);
    $test->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    app('auth')->forgetGuards();

    return $week;
}

/** A validated independent stock basket of the household holding `quantity` bottles; returns [basket id, product id]. */
function validatedStockBasketOf(object $test, string $household, string $quantity = '3'): array
{
    [$basket, $product] = draftStockBasketOf($test, $household, $quantity, validate: true);

    return [$basket, $product];
}

/** An independent stock basket draft holding `quantity` bottles priced `priceCents`; returns [basket id, product id]. */
function draftStockBasketOf(object $test, string $household, string $quantity = '3', int $priceCents = 1200, bool $validate = false): array
{
    $manager = accountWith(Role::Manager);
    $producer = ProducerModel::query()->create(['name' => 'Maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $product = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $product, 'producer_id' => $producer->id, 'stock' => true,
        'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => $priceCents, 'variable' => false, 'active' => true]);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'product_id' => $product,
        'type' => 'entry', 'quantity' => '10', 'occurred_at' => now(), 'recorded_by' => $manager->id]);
    $test->actingAs($manager)->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
    $basket = (string) DB::table('baskets')->where('household_id', $household)->where('kind', 'stock')->latest('id')->value('id');
    $test->post("/finance/baskets/{$basket}/stock-lines", ['stock_product_id' => $product, 'stock_quantity' => $quantity])->assertRedirect();
    if ($validate) {
        $test->post("/finance/baskets/{$basket}/validation")->assertRedirect();
    }
    app('auth')->forgetGuards();

    return [$basket, $product];
}

function attentionOf(object $test, User $user): array
{
    return json_decode(json_encode($test->actingAs($user)->get('/dashboard')->viewData('page')['props']['attention']), true);
}

it('renders a current member their closed-cycle basket as a collection group linking to that basket (R4, S4)', function () {
    $week = closedWeek($this);

    $this->actingAs(memberOf($week['households']['A']))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('attention', 1)
        ->where('attention.0.action', 'collect_basket')
        ->where('attention.0.cycleId', $week['cycle'])
        ->where('attention.0.count', 1)
        ->where('attention.0.href', "/baskets/{$week['baskets']['A']}"));
});

it('gives the member a no-cycle group for a stock basket and J none of H\'s work (S4)', function () {
    $week = closedWeek($this);
    [$stock] = validatedStockBasketOf($this, $week['households']['A']);

    $groups = attentionOf($this, memberOf($week['households']['A']));
    expect($groups)->toHaveCount(2)
        ->and(collect($groups)->pluck('cycleId')->all())->toBe([$week['cycle'], null])
        ->and($groups[1]['scheduledDate'])->toBeNull()
        ->and($groups[1]['href'])->toBe("/baskets/{$stock}");

    expect(attentionOf($this, memberOf($week['households']['K'])))->toBe([]);
});

it('counts the Finance member\'s own basket once in the staff-wide group and keeps a member-only actor off the staff list (S1 portion, R2, R8)', function () {
    $week = closedWeek($this);
    $financeMember = memberOf($week['households']['A']);
    DB::table('account_roles')->insert(['user_id' => $financeMember->id, 'role' => Role::Finance->value]);

    $staff = attentionOf($this, $financeMember);
    expect($staff)->toHaveCount(1)
        ->and($staff[0]['count'])->toBe(2)
        ->and($staff[0]['href'])->toBe("/finance/baskets?pending=1&cycle={$week['cycle']}");

    $memberOnly = attentionOf($this, memberOf($week['households']['A']));
    expect($memberOnly)->toHaveCount(1)
        ->and($memberOnly[0]['count'])->toBe(1)
        ->and($memberOnly[0]['href'])->toStartWith('/baskets/');
});

it('drops a collected basket and a fully reversed one, but keeps a pending basket when the wallet goes negative (R4, R8, R14)', function () {
    $week = closedWeek($this);
    [$reversed, $product] = validatedStockBasketOf($this, $week['households']['A']);
    $finance = accountWith(Role::Finance);

    $this->actingAs($finance)->post("/finance/baskets/{$reversed}/corrections", ['amount_cents' => 3600, 'reason' => 'All came back', 'correction_id' => (string) Str::uuid7(),
        'returned_goods' => [['stock_product_id' => $product, 'quantity' => '3']]])->assertRedirect();
    expect(attentionOf($this, $finance))->toHaveCount(1)
        ->and(attentionOf($this, $finance)[0]['count'])->toBe(2);

    DB::table('wallet_movements')->insert(['id' => (string) Str::uuid7(), 'household_id' => $week['households']['A'], 'type' => 'correction', 'amount_cents' => -50000,
        'occurred_at' => now(), 'recorded_by' => $finance->id, 'note' => 'Overdrawn']);
    expect(attentionOf($this, $finance)[0]['count'])->toBe(2);

    $this->put("/finance/baskets/{$week['baskets']['A']}/collection")->assertRedirect();
    expect(attentionOf($this, $finance)[0]['count'])->toBe(1);
    $this->put("/finance/baskets/{$week['baskets']['B']}/collection")->assertRedirect();
    expect(attentionOf($this, $finance))->toBe([]);
});

it('reads without writing and links only to existing GET screens (R3)', function () {
    $week = closedWeek($this);
    validatedStockBasketOf($this, $week['households']['A']);
    $finance = accountWith(Role::Finance);
    $tables = ['baskets', 'basket_lines', 'basket_adjustments', 'basket_collection_events', 'wallet_movements', 'stock_movements', 'cycles', 'orders'];
    $snapshot = fn () => array_map(fn (string $table) => DB::table($table)->orderBy('id')->get()->toArray(), $tables);
    $before = $snapshot();

    $groups = attentionOf($this, $finance);

    expect($snapshot())->toEqual($before)
        ->and(collect($groups)->pluck('href')->every(fn (string $href) => str_starts_with($href, '/finance/baskets')))->toBeTrue();
    foreach ($groups as $group) {
        $this->get($group['href'])->assertOk();
    }
});

it('keeps an inactive household\'s validated, uncollected basket in the staff-wide group, and the group link lists the same records (R8, R14, S12)', function () {
    $week = closedWeek($this);
    DB::table('households')->where('id', $week['households']['B'])->update(['deactivated_at' => now()]);
    $finance = accountWith(Role::Finance);

    $dashboard = app(ListDashboard::class)(Actor::manager($finance->id));
    expect($dashboard->attention)->toHaveCount(1)
        ->and($dashboard->attention[0]->count)->toBe(2)
        ->and($dashboard->attention[0]->destination->staffWide)->toBeTrue();

    $groups = attentionOf($this, $finance);
    expect($groups)->toHaveCount(1)->and($groups[0]['count'])->toBe(2);
    $this->get($groups[0]['href'])->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('baskets', 2)
        ->where('baskets', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
            === collect([$week['baskets']['A'], $week['baskets']['B']])->sort()->values()->all()));
});

it('shows Finance a persisted zero-price draft with goods as validation work linking to that basket, and a member none (R7, S7)', function () {
    $week = closedWeek($this);
    [$free] = draftStockBasketOf($this, $week['households']['A'], priceCents: 0);

    expect(DB::table('baskets')->where('id', $free)->value('state'))->toBe('draft');
    $groups = attentionOf($this, accountWith(Role::Finance));
    expect(collect($groups)->pluck('action')->all())->toContain('validate_basket');
    $draft = collect($groups)->firstWhere('action', 'validate_basket');
    expect($draft['count'])->toBe(1)
        ->and($draft['cycleId'])->toBeNull()
        ->and($draft['href'])->toBe("/finance/baskets/{$free}");

    expect(collect(attentionOf($this, memberOf($week['households']['A'])))->pluck('action')->all())->not->toContain('validate_basket');
});

it('shows Finance the settled cycle\'s draft to validate, drops an emptied draft, keeps collection, and lets validation close the cycle without a dashboard command (R7, R10, S7)', function () {
    $week = lateArrivalWeek();
    [$emptied] = draftStockBasketOf($this, $week['households']['A']);
    DB::table('basket_lines')->where('basket_id', $emptied)->update(['removed_at' => now()]);
    $finance = accountWith(Role::Finance);

    $groups = collect(attentionOf($this, $finance))->keyBy('action');
    expect($groups->keys()->sort()->values()->all())->toBe(['collect_basket', 'validate_basket'])
        ->and($groups['validate_basket']['count'])->toBe(1)
        ->and($groups['validate_basket']['cycleId'])->toBe($week['cycle'])
        ->and($groups['validate_basket']['href'])->toBe("/finance/baskets/{$week['baskets']['B']}")
        ->and($groups['collect_basket']['count'])->toBe(1);

    $this->actingAs($finance)->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    app('auth')->forgetGuards();

    expect(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->not->toBeNull();
    $after = collect(attentionOf($this, $finance));
    expect($after->pluck('action')->all())->toBe(['collect_basket'])
        ->and($after[0]['count'])->toBe(2);
    $this->actingAs($finance)->get('/dashboard')->assertOk();
    expect(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->not->toBeNull();
});

it('gives Receiver, Catalogue and a plain member no draft work (R7)', function () {
    $week = lateArrivalWeek();

    foreach ([accountWith(Role::Receiver), accountWith(Role::Catalogue), memberOf($week['households']['B'])] as $account) {
        expect(collect(attentionOf($this, $account))->pluck('action')->all())->not->toContain('validate_basket', 'prepare_basket');
    }
});

it('links several drafts to the unfiltered staff basket list (R7)', function () {
    $week = lateArrivalWeek();
    draftStockBasketOf($this, $week['households']['A']);
    draftStockBasketOf($this, $week['households']['B']);

    $noCycle = collect(attentionOf($this, accountWith(Role::Finance)))->where('action', 'validate_basket')->firstWhere('cycleId', null);
    expect($noCycle['href'])->toBe('/finance/baskets')->and($noCycle['count'])->toBe(2);
});
