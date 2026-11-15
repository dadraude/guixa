<?php

use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps a closed cycle showing its pending weekly baskets with a link, until real collection removes them without reopening or charging (R23a, R24, R24b, S54, S83)', function () {
    $week = lateArrivalWeek();
    $this->post("/finance/baskets/{$week['baskets']['B']}/validation")->assertRedirect();
    $closedAt = DB::table('cycles')->where('id', $week['cycle'])->value('closed_at');
    expect($closedAt)->not->toBeNull();
    $ledger = fn () => [DB::table('wallet_movements')->orderBy('id')->get(), DB::table('stock_movements')->orderBy('id')->get()];
    $before = $ledger();
    $link = fn (int $count) => $count === 0 ? null : "/finance/baskets?pending=1&cycle={$week['cycle']}";

    foreach ([2, 1] as $expected) {
        $this->get('/manage/cycles')->assertInertia(fn (Assert $page) => $page
            ->where('pendingCollections', fn ($counts) => ($counts[$week['cycle']] ?? 0) === $expected));
        $this->get("/manage/cycles/{$week['cycle']}")->assertInertia(fn (Assert $page) => $page
            ->where('pendingCollections.count', $expected)
            ->where('pendingCollections.href', $link($expected)));
        $this->get('/finance/baskets?pending=1&cycle='.$week['cycle'])->assertInertia(fn (Assert $page) => $page
            ->has('baskets', $expected));
        if ($expected === 2) {
            $this->put("/finance/baskets/{$week['baskets']['A']}/collection")->assertRedirect();
        }
    }

    expect(DB::table('cycles')->where('id', $week['cycle'])->value('closed_at'))->toBe($closedAt)
        ->and($ledger())->toEqual($before);
});

it('shows pending collections by validation order to the right audience: drafts excluded, cycleless and late included, members scoped to their household (R24, R35, S42, S54)', function () {
    $week = lateArrivalWeek();
    $longName = 'Cooperativa '.str_repeat('Llarga ', 18).'de Veïns';
    DB::table('households')->where('id', $week['households']['A'])->update(['name' => $longName]);
    $item = app(CreateReturnItem::class)(Actor::manager($week['manager']->id), 'Glass jar', 50);
    $stock = app(CreateStockBasket::class)(Actor::manager($week['manager']->id), $week['households']['A']);
    $this->post("/finance/baskets/{$stock}/return-lines", ['return_item_id' => $item, 'return_quantity' => '2'])->assertRedirect();
    $draftOnly = (string) $week['baskets']['B'];

    // Before B is validated the cycle counts only A: a draft is preparation, not pending collection.
    $this->get("/manage/cycles/{$week['cycle']}")->assertInertia(fn (Assert $page) => $page->where('pendingCollections.count', 1));
    $this->get('/finance/baskets?pending=1')->assertInertia(fn (Assert $page) => $page
        ->has('baskets', 1)->where('baskets.0.id', $week['baskets']['A'])->where('baskets.0.householdName', 'Household A'));
    $this->post("/finance/baskets/{$stock}/validation")->assertRedirect();
    $this->post("/finance/baskets/{$draftOnly}/validation")->assertRedirect();

    $ids = collect(DB::table('baskets')->where('state', 'validated')->orderBy('validated_at')->orderBy('id')->pluck('id'))->all();
    expect($ids)->toHaveCount(3);
    $validatedAt = fn (string $id) => Carbon::parse(DB::table('baskets')->where('id', $id)->value('validated_at'))
        ->setTimezone(config('guixa.timezone'))->format('Y-m-d H:i');
    expect(DB::table('baskets')->where('id', $stock)->value('household_name'))->toBe($longName);
    $this->get('/finance/baskets')->assertInertia(fn (Assert $page) => $page->where('pending', false));
    foreach ([accountWith(Role::Manager), accountWith(Role::Finance)] as $staff) {
        $this->actingAs($staff)->get('/finance/baskets?pending=1')->assertInertia(fn (Assert $page) => $page
            ->has('baskets', 3)->where('pending', true)
            ->where('baskets', fn ($rows) => collect($rows)->pluck('id')->all() === $ids)
            ->where('baskets', fn ($rows) => collect($rows)->pluck('validatedAt')->all() === array_map($validatedAt, $ids)));
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('attention', fn ($groups) => collect($groups)->sum('count') === 3));
    }
    // The cycle badge counts weekly baskets only; the cycleless stock basket is not in it.
    $this->actingAs(accountWith(Role::Manager))->get("/manage/cycles/{$week['cycle']}")
        ->assertInertia(fn (Assert $page) => $page->where('pendingCollections.count', 2));

    $anna = memberOf($week['households']['A'], 'Anna');
    $this->actingAs($anna)->get('/baskets?pending=1')->assertInertia(fn (Assert $page) => $page
        ->has('baskets', 2)->where('baskets', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
            === collect([$stock, $week['baskets']['A']])->sort()->values()->all())
        ->where('baskets', fn ($rows) => collect($rows)->every(fn ($row) => $row['validatedAt'] === $validatedAt($row['id']))));
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('attention', fn ($groups) => collect($groups)->sum('count') === 2 && collect($groups)->every(fn ($group) => str_starts_with($group['href'], '/baskets'))));
    $this->actingAs(memberOf($week['households']['B'], 'Berta'))->get('/baskets?pending=1')->assertInertia(fn (Assert $page) => $page
        ->has('baskets', 1)->where('baskets.0.id', $draftOnly));
    $this->actingAs(memberOf($week['households']['K'], 'Kira'))->get('/baskets?pending=1')->assertInertia(fn (Assert $page) => $page
        ->has('baskets', 0))->assertOk();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('attention', 0));
    $this->actingAs(accountWith(Role::Producer))->get('/baskets?pending=1')->assertForbidden();
    $this->get('/finance/baskets?pending=1')->assertForbidden();

    $this->actingAs($anna)->put("/baskets/{$stock}/collection")->assertRedirect();
    $this->get('/baskets?pending=1')->assertInertia(fn (Assert $page) => $page->has('baskets', 1));
});
