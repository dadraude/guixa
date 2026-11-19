<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Guixa\Catalog\Application\CreateProduct;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\FrozenClock;

it('seeds a usable cooperative with accounts, orders, reception and pickups', function () {
    Mail::fake();
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(11);
    $this->assertDatabaseCount('households', 5);
    $this->assertDatabaseCount('producers', 5);
    $this->assertDatabaseCount('products', 13);
    $this->assertDatabaseCount('cycles', 3);
    $this->assertDatabaseCount('orders', 12);
    expect(DB::table('order_lines')->count())->toBeGreaterThan(60);
    expect(DB::table('cycles')->whereNotNull('settled_at')->count())->toBe(1);
    expect(DB::table('baskets')->where('kind', 'weekly')->where('state', 'draft')->count())->toBe(2);
    expect(DB::table('baskets')->where('kind', 'weekly')->where('state', 'validated')->count())->toBe(1);
    expect(DB::table('baskets')->where('kind', 'weekly')->where('state', 'collected')->count())->toBe(1);
    expect(DB::table('received_totals')->count())->toBeGreaterThan(0);
    expect(DB::table('stock_movements')->count())->toBe(56);
    expect(DB::table('wallet_movements')->count())->toBeGreaterThanOrEqual(5);
    Mail::assertNothingSent();
});

it('seeds one reception in each state the dashboard lists and reads them without writing', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $cycle = DB::table('cycles')->whereNull('settled_at')->orderBy('pickup_date')->value('id');
    $producers = DB::table('producers')->orderBy('name')->pluck('id', 'name');
    $validated = $producers['Hort de la Vall'];
    $unopened = $producers['Forn del Barri'];
    $defaulted = $producers['La Formatgeria'];

    expect(DB::table('cycle_producers')->where('cycle_id', $cycle)->where('producer_id', $validated)->value('validated_at'))->not->toBeNull()
        ->and(DB::table('cycle_producers')->where('cycle_id', $cycle)->where('producer_id', $defaulted)->value('validated_at'))->toBeNull()
        ->and(DB::table('received_totals')->where('cycle_id', $cycle)->where('producer_id', $defaulted)->count())->toBeGreaterThan(0)
        ->and(DB::table('received_totals')->where('cycle_id', $cycle)->where('producer_id', $unopened)->count())->toBe(0);

    $totals = DB::table('received_totals')->count();
    $manager = User::query()->where('email', 'gestora@demo.guixa.test')->firstOrFail();
    $actions = collect($this->actingAs($manager)->get('/dashboard')->viewData('page')['props']['attention'])->keyBy('action');
    expect($actions['mark_distributed']->href)->toBe("/receptions/{$cycle}/{$validated}")
        ->and($actions['check_delivery_and_allocations']->count)->toBe(2)
        ->and(DB::table('received_totals')->count())->toBe($totals);

    // Nobody holds Receiver; Finance, which can receive and settle, is the demo account that sees the reception work.
    $finance = User::query()->where('email', 'anna@demo.guixa.test')->firstOrFail();
    $financeActions = collect($this->actingAs($finance)->get('/dashboard')->viewData('page')['props']['attention'])->pluck('action')->all();
    expect($financeActions)->toContain('check_delivery_and_allocations')->toContain('mark_distributed');
});

it('seeds one closed, ordered, unsent producer with an account as the manager\'s only manual send, and shows it to no other role (R9, S8)', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $cycle = DB::table('cycles')->orderByDesc('pickup_date')->value('id');
    $manager = User::query()->where('email', 'gestora@demo.guixa.test')->firstOrFail();

    $sends = collect($this->actingAs($manager)->get('/dashboard')->viewData('page')['props']['attention'])->where('action', 'send_order');

    expect($sends)->toHaveCount(1)
        ->and($sends->first()->count)->toBe(1)
        ->and($sends->first()->href)->toBe('/manage/cycles');

    foreach (['anna@demo.guixa.test', 'biel@demo.guixa.test'] as $email) {
        app('auth')->forgetGuards();
        $user = User::query()->where('email', $email)->firstOrFail();
        expect(collect($this->actingAs($user)->get('/dashboard')->viewData('page')['props']['attention'])->pluck('action'))->not->toContain('send_order');
    }
});

it('shows Finance the demo cycle\'s drafts as validation work next to the pending collection', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $finance = User::query()->where('email', 'anna@demo.guixa.test')->firstOrFail();

    $actions = collect($this->actingAs($finance)->get('/dashboard')->viewData('page')['props']['attention'])->keyBy('action');

    expect($actions['validate_basket']->count)->toBe(2)
        ->and($actions['validate_basket']->href)->toBe('/finance/baskets')
        ->and($actions->has('collect_basket'))->toBeTrue()
        ->and($actions->has('prepare_basket'))->toBeFalse();
});

it('shows each demo role its own opportunity below no false task: member ordering, producer review, Catalogue maintenance (R11, S3, S10)', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $kinds = function (string $email) {
        app('auth')->forgetGuards();
        $user = User::query()->where('email', $email)->firstOrFail();

        return collect($this->actingAs($user)->get('/dashboard')->viewData('page')['props']['opportunities']);
    };

    $member = $kinds('carla@demo.guixa.test');
    expect($member->pluck('kind')->all())->toBe(['member_order'])
        ->and($member->first()->cutoffs)->not->toBeEmpty()
        ->and($kinds('hort@demo.guixa.test')->pluck('kind')->unique()->all())->toBe(['producer_summary'])
        ->and($kinds('biel@demo.guixa.test')->pluck('kind')->all())->toContain('catalogue_maintenance');
});

it('seeds the Catalogue states', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $deactivated = DB::table('producers')->where('active', false)->pluck('id');
    $unsentCycle = DB::table('cycles')->orderByDesc('pickup_date')->value('id');

    expect($deactivated)->toHaveCount(1)
        ->and(DB::table('cycle_producers')->where('producer_id', $deactivated->first())->count())->toBe(0);

    $inactiveProducts = DB::table('products')->where('active', false)->where('stock', false)->pluck('id');
    expect($inactiveProducts)->toHaveCount(2)
        ->and(DB::table('order_lines')->whereIn('product_id', $inactiveProducts)->count())->toBe(0);

    $unavailable = DB::table('product_unavailabilities')->get();
    expect($unavailable)->toHaveCount(1)
        ->and($unavailable->first()->cycle_id)->toBe($unsentCycle)
        ->and(DB::table('cycle_producers')->where('cycle_id', $unsentCycle)
            ->where('producer_id', DB::table('products')->where('id', $unavailable->first()->product_id)->value('producer_id'))
            ->value('sent_at'))->toBeNull();

    expect(DB::table('return_items')->count())->toBe(2)
        ->and(DB::table('return_items')->where('active', false)->count())->toBe(1);

    $this->actingAs(User::query()->where('email', 'biel@demo.guixa.test')->firstOrFail());
    $producer = DB::table('products')->where('id', $unavailable->first()->product_id)->value('producer_id');
    foreach (['/catalogue/producers', "/catalogue/producers/{$producer}/products", "/catalogue/producers/{$producer}/availability", '/catalogue/return-items'] as $path) {
        $this->get($path)->assertOk();
    }
});

it('seeds the Producer states', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $producers = DB::table('producers')->pluck('id', 'name');
    $hort = $producers['Hort de la Vall'];
    $movements = fn (string $productId) => DB::table('stock_movements')->where('product_id', $productId)->count();

    $empty = DB::table('producers')->whereNotIn('id', DB::table('products')->select('producer_id'))->where('active', true)->pluck('id');
    expect($empty)->toHaveCount(1)
        ->and(DB::table('cycle_producers')->where('producer_id', $empty->first())->count())->toBe(0)
        ->and(DB::table('users')->where('producer_id', $empty->first())->count())->toBe(1);

    $stock = DB::table('products')->where('producer_id', $hort)->where('stock', true)->get()->keyBy('name');
    $long = $stock->first(fn ($product) => strlen($product->name) >= 60);
    expect($long)->not->toBeNull()
        ->and(strlen($long->unit))->toBeGreaterThanOrEqual(20)
        ->and($movements($long->id))->toBe(0)
        ->and($long->active)->toBeTrue();

    $soldOut = $stock['Llenties de la Cerdanya'];
    expect($soldOut->active)->toBeTrue()
        ->and($movements($soldOut->id))->toBe(2)
        ->and((float) DB::table('stock_movements')->where('product_id', $soldOut->id)->sum('quantity'))->toBe(0.0);
    expect($stock->where('active', false)->keys()->all())->toBe(['Fesols de Santa Pau'])
        ->and($movements($stock['Fesols de Santa Pau']->id))->toBe(1);

    $longHistory = $stock['Arròs integral'];
    expect($movements($longHistory->id))->toBeGreaterThanOrEqual(52)
        ->and($movements($stock['Cigrons cuits']->id))->toBe(1);

    $inactiveWeekly = DB::table('products')->where('stock', false)->where('active', false)->get();
    expect($inactiveWeekly->pluck('name')->sort()->values()->all())->toBe(['Coca de recapte', 'Coca de sucre'])
        ->and($inactiveWeekly->pluck('producer_id')->unique()->all())->toBe([$producers['Forn del Barri']])
        ->and(DB::table('order_lines')->whereIn('product_id', $inactiveWeekly->pluck('id'))->count())->toBe(0);
});

it('seeds staff role holders besides the manager', function () {
    $this->seed(DemoSeeder::class);

    $holders = DB::table('account_roles')->join('users', 'users.id', '=', 'account_roles.user_id')
        ->whereIn('role', ['finance', 'catalogue', 'receiver'])
        ->orderBy('users.email')->get()->groupBy('role')->map(fn ($rows) => $rows->pluck('email')->all())->sortKeys()->all();

    expect($holders)->toBe([
        'catalogue' => ['biel@demo.guixa.test'],
        'finance' => ['anna@demo.guixa.test'],
    ]);
    expect(DB::table('role_changes')->count())->toBe(2);
});

it('seeds the Manager states: a role with no holder, a deactivated household and a deactivated member account (R27)', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $manager = User::query()->where('email', 'gestora@demo.guixa.test')->firstOrFail();

    $groups = collect($this->actingAs($manager)->get('/manage/roles')->viewData('page')['props']['groups'])->keyBy('role');
    expect($groups['receiver']->holders)->toBe([])
        ->and($groups->only(['manager', 'finance', 'catalogue'])->every(fn ($group) => $group->holders !== []))->toBeTrue();

    $household = DB::table('households')->whereNotNull('deactivated_at')->get();
    expect($household)->toHaveCount(1)
        ->and(DB::table('users')->where('household_id', $household->first()->id)->count())->toBe(0);
    $this->get("/manage/households/{$household->first()->id}")->assertOk();

    $account = DB::table('users')->whereNotNull('deactivated_at')->get();
    expect($account)->toHaveCount(1)
        ->and($account->first()->household_id)->toBeNull()
        ->and(DB::table('users')->where('email', 'anna@demo.guixa.test')->value('household_id'))->not->toBeNull();
    $accounts = collect($this->get('/manage/accounts')->viewData('page')['props']['accounts'])->keyBy('email');
    expect($accounts['jan@demo.guixa.test']->active)->toBeFalse()
        ->and($accounts['anna@demo.guixa.test']->active)->toBeTrue();
});

it('shows the combined member and Finance demo account one collection group, the unlinked account an explanation, and caught-up members their opportunity instead (S1, S10, R14)', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $props = function (string $email) {
        app('auth')->forgetGuards();

        return $this->actingAs(User::query()->where('email', $email)->firstOrFail())->get('/dashboard')->viewData('page')['props'];
    };

    $combined = $props('anna@demo.guixa.test');
    $collections = collect($combined['attention'])->where('action', 'collect_basket');
    expect($collections)->toHaveCount(1)
        ->and($combined['can']['showCaughtUp'])->toBeFalse()
        ->and(collect($combined['attention'])->pluck('key')->unique()->count())->toBe(count($combined['attention']));

    $unlinked = $props('eva@demo.guixa.test');
    expect($unlinked['attention'])->toBe([])
        ->and($unlinked['opportunities'])->toBe([])
        ->and($unlinked['can'])->toMatchArray(['showCaughtUp' => false, 'showNoContext' => true])
        ->and(DB::table('users')->where('email', 'eva@demo.guixa.test')->value('household_id'))->toBeNull()
        ->and(DB::table('account_roles')->join('users', 'users.id', '=', 'account_roles.user_id')->where('email', 'eva@demo.guixa.test')->count())->toBe(0);

    foreach (['carla@demo.guixa.test', 'david@demo.guixa.test'] as $email) {
        $caughtUp = $props($email);
        expect($caughtUp['attention'])->toBe([])
            ->and($caughtUp['can'])->toMatchArray(['showCaughtUp' => true, 'showNoContext' => false])
            ->and(collect($caughtUp['opportunities'])->pluck('kind')->all())->toBe(['member_order']);
    }
});

it('lets every demo role log in and open its main screens', function (string $email, array $paths) {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();

    $this->post('/login', ['email' => $email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs(User::query()->where('email', $email)->firstOrFail());

    foreach ($paths as $path) {
        $this->get($path)->assertOk();
    }

    if ($email === 'anna@demo.guixa.test') {
        foreach (DB::table('cycles')->pluck('id') as $cycleId) {
            $this->get(route('orders.edit', $cycleId))->assertOk();
        }
        $this->get(route('receptions.show', [DB::table('cycles')->whereNotNull('settled_at')->value('id'), DB::table('producers')->value('id')]))->assertOk();
    }
    if ($email === 'gestora@demo.guixa.test') {
        $cycle = DB::table('cycles')->whereNotNull('settled_at')->value('id');
        $producer = DB::table('producers')->value('id');
        $this->get(route('receptions.show', [$cycle, $producer]))->assertOk();
        $this->get('/manage/cycles/'.DB::table('cycles')->orderByDesc('pickup_date')->value('id'))->assertOk();
        $this->get('/manage/stock/'.DB::table('products')->where('name', 'Arròs integral')->value('id').'/movements')->assertOk();
    }
    if ($email === 'hort@demo.guixa.test') {
        $cycle = DB::table('cycles')->orderBy('pickup_date')->value('id');
        $history = '/producer/stock/'.DB::table('products')->where('name', 'Arròs integral')->value('id').'/movements';
        foreach (['/producer/cutoff', "/producer/cycles/{$cycle}", $history] as $path) {
            $this->get($path)->assertOk();
        }
        expect(data_get($this->get("{$history}?page=2")->assertOk()->viewData('page')['props'], 'history.lines'))->not->toBeEmpty();
    }
})->with([
    'manager' => ['gestora@demo.guixa.test', ['/dashboard', '/manage/households', '/manage/producers', '/manage/cycles', '/manage/stock', '/manage/roles', '/manage/accounts', '/manage/settings']],
    'member and Finance' => ['anna@demo.guixa.test', ['/dashboard', '/', '/wallet', '/receptions']],
    'catalogue' => ['biel@demo.guixa.test', ['/dashboard', '/catalogue/producers']],
    'producer' => ['hort@demo.guixa.test', ['/dashboard', '/producer/products']],
    'producer without products' => ['sense-productes@demo.guixa.test', ['/dashboard', '/producer/products']],
]);

it('can be repeated without duplicating data or resetting demo edits', function () {
    $this->seed(DemoSeeder::class);
    $manager = User::query()->where('email', 'gestora@demo.guixa.test')->firstOrFail();
    $manager->forceFill(['email' => 'changed-manager@example.test'])->save();
    $user = User::query()->where('email', 'anna@demo.guixa.test')->firstOrFail();
    $user->forceFill(['name' => 'Updated demo member', 'password' => 'changed-password'])->save();
    $cycle = DB::table('cycles')->orderByDesc('pickup_date')->value('id');
    $product = DB::table('products')->where('name', 'Pastanagues')->value('id');
    app(SetOrderLine::class)(app(ActorResolver::class)->forAccount($user->id), $cycle, $product, '3.250');
    $tables = ['users', 'households', 'producers', 'products', 'cycles', 'orders', 'order_lines', 'wallet_movements', 'stock_movements', 'received_totals', 'return_items', 'product_unavailabilities'];
    $counts = array_map(fn (string $table) => DB::table($table)->count(), $tables);

    $this->seed(DemoSeeder::class);

    expect(array_map(fn (string $table) => DB::table($table)->count(), $tables))->toBe($counts);
    expect($manager->fresh()->email)->toBe('changed-manager@example.test');
    expect($user->fresh()->name)->toBe('Updated demo member');
    expect(Hash::check('changed-password', $user->fresh()->password))->toBeTrue();
    $order = DB::table('orders')->where('cycle_id', $cycle)->where('household_id', $user->household_id)->value('id');
    $this->assertDatabaseHas('order_lines', ['order_id' => $order, 'product_id' => $product, 'ordered_quantity' => '3.250']);
});

it('preserves existing data and uses free dates in the configured pickup weekday and timezone', function () {
    $this->app->instance(Clock::class, new FrozenClock('2026-10-29 23:30', 'Pacific/Auckland'));
    $existing = User::factory()->manager()->create(['email' => 'existing@example.test']);
    DB::table('cooperative_settings')->where('id', 1)->update(['pickup_weekday' => 2]);
    $existingCycle = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $existingCycle, 'pickup_date' => '2026-11-03']);

    $this->seed(DemoSeeder::class);

    expect(User::query()->findOrFail($existing->id)->email)->toBe('existing@example.test');
    $this->assertDatabaseHas('cycles', ['id' => $existingCycle, 'pickup_date' => '2026-11-03']);
    expect(DB::table('cooperative_settings')->value('pickup_weekday'))->toBe(2);
    $dates = DB::table('cycles')->where('id', '<>', $existingCycle)->pluck('pickup_date');
    expect($dates)->toHaveCount(3);
    foreach ($dates as $date) {
        expect((int) (new DateTimeImmutable($date))->format('N'))->toBe(2);
        expect($date)->toBeGreaterThan('2026-10-29');
    }
});

it('refuses to create demo credentials outside local and testing environments', function () {
    $this->app->instance('env', 'production');

    expect(fn () => app(DemoSeeder::class)->run())->toThrow(LogicException::class);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('households', 0);
});

it('rolls back the entire demo when one part cannot be created', function () {
    $this->app->bind(CreateProduct::class, fn () => throw new LogicException('Demo product failed.'));

    expect(fn () => app(DemoSeeder::class)->run())->toThrow(LogicException::class, 'Demo product failed.');

    foreach (['users', 'account_roles', 'households', 'producers', 'products', 'cycles', 'orders', 'wallet_movements'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect(DB::table('cooperative_settings')->value('pickup_weekday'))->toBeNull();
});
