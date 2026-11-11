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

    expect(User::count())->toBe(8);
    $this->assertDatabaseCount('households', 4);
    $this->assertDatabaseCount('producers', 3);
    $this->assertDatabaseCount('products', 8);
    $this->assertDatabaseCount('cycles', 3);
    $this->assertDatabaseCount('orders', 12);
    expect(DB::table('order_lines')->count())->toBeGreaterThan(60);
    expect(DB::table('cycles')->whereNotNull('settled_at')->count())->toBe(1);
    expect(DB::table('baskets')->where('kind', 'weekly')->where('state', 'draft')->count())->toBe(2);
    expect(DB::table('baskets')->where('kind', 'weekly')->where('state', 'validated')->count())->toBe(1);
    expect(DB::table('baskets')->where('kind', 'weekly')->where('state', 'collected')->count())->toBe(1);
    expect(DB::table('received_totals')->count())->toBeGreaterThan(0);
    expect(DB::table('stock_movements')->count())->toBe(2);
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

    $receiver = User::query()->where('email', 'biel@demo.guixa.test')->firstOrFail();
    $receiverActions = collect($this->actingAs($receiver)->get('/dashboard')->viewData('page')['props']['attention'])->pluck('action')->all();
    expect($receiverActions)->toContain('check_delivery_and_allocations')->not->toContain('mark_distributed');
});

it('seeds one closed, ordered, unsent producer with an account as the manager\'s only manual send, and shows it to no other role (R9, S8)', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $cycle = DB::table('cycles')->orderByDesc('pickup_date')->value('id');
    $manager = User::query()->where('email', 'gestora@demo.guixa.test')->firstOrFail();

    $sends = collect($this->actingAs($manager)->get('/dashboard')->viewData('page')['props']['attention'])->where('action', 'send_order');

    expect($sends)->toHaveCount(1)
        ->and($sends->first()->count)->toBe(1)
        ->and($sends->first()->href)->toBe("/manage/cycles/{$cycle}");

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

it('seeds staff role holders besides the manager', function () {
    $this->seed(DemoSeeder::class);

    $holders = DB::table('account_roles')->join('users', 'users.id', '=', 'account_roles.user_id')
        ->whereIn('role', ['finance', 'catalogue', 'receiver'])
        ->orderBy('role')->pluck('users.email', 'role')->all();

    expect($holders)->toBe([
        'catalogue' => 'biel@demo.guixa.test',
        'finance' => 'anna@demo.guixa.test',
        'receiver' => 'biel@demo.guixa.test',
    ]);
    expect(DB::table('role_changes')->count())->toBe(3);
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
    }
    if ($email === 'gestora@demo.guixa.test') {
        $cycle = DB::table('cycles')->whereNotNull('settled_at')->value('id');
        $producer = DB::table('producers')->value('id');
        $this->get(route('receptions.show', [$cycle, $producer]))->assertOk();
    }
    if ($email === 'biel@demo.guixa.test') {
        $cycle = DB::table('cycles')->whereNotNull('settled_at')->value('id');
        $producer = DB::table('producers')->value('id');
        $this->get(route('receptions.show', [$cycle, $producer]))->assertOk();
    }
})->with([
    'manager' => ['gestora@demo.guixa.test', ['/dashboard', '/manage/households', '/manage/producers', '/manage/cycles', '/manage/stock']],
    'member' => ['anna@demo.guixa.test', ['/dashboard', '/', '/wallet']],
    'receiver' => ['biel@demo.guixa.test', ['/dashboard', '/receptions']],
    'producer' => ['hort@demo.guixa.test', ['/dashboard', '/producer/products']],
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
    $tables = ['users', 'households', 'producers', 'products', 'cycles', 'orders', 'order_lines', 'wallet_movements', 'stock_movements', 'received_totals'];
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
