<?php

use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('lets Finance and Manager set the actual quantity of a draft weekly line, zero included, keeping order and allocation (R19, S14)', function () {
    $week = twoHouseholdWeek();
    $url = "/finance/baskets/{$week['baskets']['A']}/weekly-lines/{$week['loaf']}";

    $this->actingAs($week['manager'])->put($url, ['quantity' => '3'])->assertRedirect()->assertSessionHasNoErrors();
    $line = DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->first();
    expect([(float) $line->ordered_quantity, (float) $line->allocated_quantity, (float) $line->quantity])->toBe([5.0, 4.0, 3.0]);

    $this->actingAs(accountWith(Role::Finance))->put($url, ['quantity' => '0'])->assertRedirect()->assertSessionHasNoErrors();
    expect((float) DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->value('quantity'))->toBe(0.0);

    $this->get("/finance/baskets/{$week['baskets']['A']}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Finance/Baskets/Show')
        ->where('basket.kind', 'weekly')
        ->where('basket.weeklyLines.0.ordered', '5.000')
        ->where('basket.weeklyLines.0.allocated', '4.000')
        ->where('basket.weeklyLines.0.quantity', '0.000')
        ->where('basket.weeklyLines.0.differsFromAllocation', true));
});

it('rejects a negative, malformed or fractional-for-whole-unit actual quantity with a visible error and stores nothing (R9, R19)', function (string $quantity, string $errorKey) {
    $week = twoHouseholdWeek();

    $this->actingAs($week['manager'])->put("/finance/baskets/{$week['baskets']['A']}/weekly-lines/{$week['loaf']}", ['quantity' => $quantity])
        ->assertSessionHasErrors($errorKey);

    expect((float) DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->value('quantity'))->toBe(4.0);
})->with([['-1', 'quantity'], ['abc', 'quantity'], ['1.5', 'domain']]);

it('keeps actual quantities to Finance and Manager and refuses them after validation (R19a)', function () {
    $week = twoHouseholdWeek();
    $url = "/finance/baskets/{$week['baskets']['A']}/weekly-lines/{$week['loaf']}";

    app('auth')->forgetGuards();
    $this->put($url, ['quantity' => '1'])->assertRedirect(route('login'));
    $this->actingAs(memberOf($week['households']['A']))->put($url, ['quantity' => '1'])->assertForbidden();
    $this->actingAs(accountWith(Role::Producer))->put($url, ['quantity' => '1'])->assertForbidden();

    $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
    $this->put($url, ['quantity' => '1'])->assertSessionHasErrors('domain');
    expect((float) DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->value('quantity'))->toBe(4.0);
});

it('charges the actual quantity once and keeps validation and collection as separate steps (R22, R24, S15)', function () {
    $week = twoHouseholdWeek();
    $this->actingAs($week['manager'])->put("/finance/baskets/{$week['baskets']['A']}/weekly-lines/{$week['loaf']}", ['quantity' => '3'])->assertRedirect();

    $this->put("/finance/baskets/{$week['baskets']['A']}/collection")->assertSessionHasErrors('domain');
    $this->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();

    expect(DB::table('wallet_movements')->where('basket_id', $week['baskets']['A'])->pluck('amount_cents')->all())->toBe([-1050])
        ->and(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->toBeNull();

    $this->put("/finance/baskets/{$week['baskets']['A']}/collection")->assertRedirect();
    expect(DB::table('wallet_movements')->count())->toBe(1);
});

it('shows a member ordered, allocated and actual quantities of their own weekly basket only, read-only (R23a, R35)', function () {
    $week = twoHouseholdWeek();
    $this->actingAs($week['manager'])->put("/finance/baskets/{$week['baskets']['A']}/weekly-lines/{$week['loaf']}", ['quantity' => '3'])->assertRedirect();
    app('auth')->forgetGuards();
    $anna = memberOf($week['households']['A']);

    $this->actingAs($anna)->get("/baskets/{$week['baskets']['A']}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Member/Baskets/Show')
        ->where('basket.weeklyLines.0.ordered', '5.000')
        ->where('basket.weeklyLines.0.quantity', '3.000'));
    $this->get("/baskets/{$week['baskets']['B']}")->assertNotFound();
    $this->get("/cycles/{$week['cycle']}/order")->assertInertia(fn (Assert $page) => $page->where('order.basketId', $week['baskets']['A']));
});

it('redirects the old household pickup URL to its basket and retires the cycle pickups index (R19, R24)', function () {
    $week = twoHouseholdWeek();

    $this->actingAs($week['manager'])->get("/manage/cycles/{$week['cycle']}/pickups/{$week['households']['A']}")
        ->assertRedirect(route('finance.baskets.show', $week['baskets']['A']));
    $this->get("/manage/cycles/{$week['cycle']}/pickups/".Str::uuid7())->assertNotFound();
    $this->get("/manage/cycles/{$week['cycle']}/pickups")->assertNotFound();
    expect(Route::has('manage.cycles.pickups.index'))->toBeFalse();
});

it('keeps the unvalidated base in step with a reopened reception and refuses validation until it is distributed again (R18, S29, S30)', function () {
    $week = twoHouseholdWeek();
    $reception = "/manage/cycles/{$week['cycle']}/receptions/{$week['producer']}";
    $this->actingAs($week['manager'])->put("/finance/baskets/{$week['baskets']['B']}/weekly-lines/{$week['loaf']}", ['quantity' => '1'])->assertRedirect();

    $this->delete("{$reception}/distribution")->assertRedirect();
    $this->put("{$reception}/products/{$week['loaf']}/received", ['received' => '5'])->assertRedirect();

    $lineB = DB::table('basket_lines')->where('basket_id', $week['baskets']['B'])->first();
    $lineA = DB::table('basket_lines')->where('basket_id', $week['baskets']['A'])->first();
    // B's override is reset only if its allocation changed; here 6 -> 5 gives A 4 -> 4 and B 2 -> 1 (largest remainder).
    expect([(float) $lineA->allocated_quantity, (float) $lineB->allocated_quantity])->toBe([4.0, 1.0])
        ->and((float) $lineB->quantity)->toBe(1.0);

    $this->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertSessionHasErrors(['domain' => __('errors.ReceptionReopened')]);
    expect(DB::table('wallet_movements')->count())->toBe(0);

    $this->post("{$reception}/validate")->assertRedirect();
    $this->post("{$reception}/distribution")->assertRedirect();
    $this->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect()->assertSessionHasNoErrors();
});

it('rejects redistributing a product a validated basket contains and leaves the total, allocations, baskets and Wallet unchanged (R18, R23, S63, S89)', function () {
    $week = twoHouseholdWeek();
    $reception = "/manage/cycles/{$week['cycle']}/receptions/{$week['producer']}";
    $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
    $this->delete("{$reception}/distribution")->assertRedirect();
    $observe = fn () => [
        DB::table('received_totals')->orderBy('product_id')->get()->map(fn ($row) => (array) $row)->all(),
        DB::table('order_lines')->orderBy('id')->pluck('allocated_quantity', 'id')->all(),
        DB::table('baskets')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        DB::table('basket_lines')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        DB::table('wallet_movements')->count(),
        DB::table('basket_adjustments')->count(),
    ];
    $before = $observe();

    $this->put("{$reception}/products/{$week['loaf']}/received", ['received' => '7'])->assertSessionHasErrors(['domain' => __('errors.RedistributionBlockedByValidatedBasket')]);

    expect($observe())->toBe($before)
        ->and(DB::table('wallet_movements')->count())->toBe(1)
        ->and(DB::table('basket_adjustments')->count())->toBe(0);
});

it('rejects a hand-set allocation of a product a validated basket contains with the Catalan error and changes nothing (R16, R18, R23, S63)', function () {
    $week = twoHouseholdWeek();
    $reception = "/manage/cycles/{$week['cycle']}/receptions/{$week['producer']}";
    $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
    $this->delete("{$reception}/distribution")->assertRedirect();
    $before = [DB::table('order_lines')->orderBy('id')->pluck('allocated_quantity', 'id')->all(), DB::table('basket_lines')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), DB::table('wallet_movements')->count()];

    $this->put("{$reception}/products/{$week['loaf']}/allocations/{$week['households']['B']}", ['quantity' => '3'])
        ->assertSessionHasErrors(['domain' => __('errors.RedistributionBlockedByValidatedBasket')]);

    expect([DB::table('order_lines')->orderBy('id')->pluck('allocated_quantity', 'id')->all(), DB::table('basket_lines')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), DB::table('wallet_movements')->count()])->toBe($before);
});
