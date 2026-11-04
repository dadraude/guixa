<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('denies a member the basket page and the collection once the household is deactivated, the member moved or the account deactivated (R35)', function (string $change, array $expected) {
    $week = twoHouseholdWeek(500);
    $manager = $week['manager'];
    [$home, $other] = [$week['households']['A'], $week['households']['B']];
    $basketId = $week['baskets']['A'];
    $member = User::query()->where('household_id', $home)->firstOrFail();
    $this->actingAs($manager)->post(route('finance.baskets.validate', $basketId))->assertRedirect()->assertSessionHasNoErrors();
    $state = fn () => DB::table('baskets')->where('id', $basketId)->value('state');
    $events = fn () => DB::table('basket_collection_events')->where('basket_id', $basketId)->count();

    $this->actingAs($member)->get(route('baskets.show', $basketId))->assertOk();

    match ($change) {
        'household deactivated' => $this->actingAs($manager)->post(route('manage.households.deactivate', $home)),
        'member moved' => $this->actingAs($manager)->put(route('manage.accounts.household.update', $member->id), ['household_id' => $other]),
        'account deactivated' => $this->actingAs($manager)->post(route('manage.accounts.deactivation.store', $member->id)),
    };
    app('auth')->forgetGuards();

    $show = $this->actingAs($member->fresh())->get(route('baskets.show', $basketId));
    $collect = $this->actingAs($member->fresh())->put(route('baskets.collect', $basketId));
    expect([$show->status(), $collect->status()])->toBe($expected);
    if ($expected[0] === 302) {
        $show->assertRedirect(route('login'));
    }
    expect($state())->toBe('validated')
        ->and($events())->toBe(0);
})->with([
    'household deactivated' => ['household deactivated', [403, 403]],
    'member moved' => ['member moved', [404, 403]],
    'account deactivated' => ['account deactivated', [302, 302]],
]);
