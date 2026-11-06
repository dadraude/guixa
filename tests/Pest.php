<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Application', 'Unit/Shared');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Bounded contexts under src/ (directory names). `Shared` is a kernel, not a context, so it is
 * left out unless the rule applies to it too.
 *
 * @return list<string>
 */
function architectureContexts(bool $includeShared = false): array
{
    $contexts = array_map('basename', glob(dirname(__DIR__).'/src/*', GLOB_ONLYDIR) ?: []);

    return array_values(array_filter(
        $contexts,
        fn (string $context) => $includeShared || $context !== 'Shared',
    ));
}

/**
 * Classes declared (concrete ones only unless told otherwise) under a Guixa namespace.
 *
 * @return list<class-string>
 */
function architectureClasses(string $namespace, bool $concreteOnly = true): array
{
    $directory = dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($namespace, strlen('Guixa\\')));

    if (! is_dir($directory)) {
        return [];
    }

    $classes = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen(dirname(__DIR__).'/src/'), -strlen('.php'));
        $class = 'Guixa\\'.str_replace('/', '\\', $relative);
        $reflection = new ReflectionClass($class);

        if ($concreteOnly && ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum())) {
            continue;
        }

        $classes[] = $class;
    }

    return $classes;
}

/** An account holding exactly the given roles. */
function accountWith(Role ...$roles): User
{
    $user = User::factory()->create();

    foreach ($roles as $role) {
        if ($role === Role::Member) {
            linkToNewHousehold($user);

            continue;
        }

        DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => $role->value]);
    }

    return $user;
}

/** A member is an account linked to a household (R35); this links it to a new one. */
function linkToNewHousehold(User $user): string
{
    $householdId = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $householdId, 'name' => 'Household '.substr($householdId, -6), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('users')->where('id', $user->id)->update(['household_id' => $householdId]);

    return $householdId;
}

/** A member account linked to the given household. */
function memberOf(string $householdId, string $name = 'Anna'): User
{
    $user = accountWith();
    $user->forceFill(['name' => $name, 'household_id' => $householdId])->save();

    return $user;
}

/** A cycle in a month, with a baker taking part (open until its cutoff, or the pickup date when it has none) and one loaf product. */
function cycleWithBaker(?CarbonImmutable $cutoff = null): array
{
    $baker = ProducerModel::query()->create([
        'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
    ]);
    $loaf = (string) Str::uuid7();
    DB::table('products')->insert([
        'id' => $loaf, 'producer_id' => $baker->id, 'name' => 'Loaf', 'unit' => 'loaf', 'price_cents' => 350, 'variable' => false, 'active' => true,
    ]);
    $cycleId = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => CarbonImmutable::now()->addDays(30)->toDateString()]);
    DB::table('cycle_producers')->insert([
        'cycle_id' => $cycleId, 'producer_id' => $baker->id, 'participating' => true, 'closes_at' => $cutoff?->toDateTimeString(),
    ]);

    return [$cycleId, $baker, $loaf];
}

/**
 * A settled cycle where household A ordered 5 loaves (EUR 3.50) and was allocated 4, and household B ordered 2 and got 2.
 *
 * @return array{cycle: string, producer: string, loaf: string, households: array<string, string>, baskets: array<string, string>, manager: User}
 */
function twoHouseholdWeek(int $loafPriceCents = 350): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    DB::table('products')->where('id', $loaf)->update(['price_cents' => $loafPriceCents]);
    $households = [];
    foreach (['A' => '5', 'B' => '2'] as $name => $quantity) {
        $households[$name] = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $households[$name], 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
        test()->actingAs(memberOf($households[$name], "Member {$name}"))->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => $quantity])->assertRedirect();
        app('auth')->forgetGuards();
    }
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);
    $manager = accountWith(Role::Manager);
    $reception = "/manage/cycles/{$cycleId}/receptions/{$baker->id}";
    test()->actingAs($manager)->put("{$reception}/products/{$loaf}/received", ['received' => '6'])->assertRedirect();
    test()->post("{$reception}/validate")->assertRedirect();
    test()->post("{$reception}/distribution")->assertRedirect();

    $baskets = [];
    foreach ($households as $name => $household) {
        $baskets[$name] = (string) DB::table('baskets')->where('household_id', $household)->value('id');
    }

    return ['cycle' => $cycleId, 'producer' => $baker->id, 'loaf' => $loaf, 'households' => $households, 'baskets' => $baskets, 'manager' => $manager];
}

/**
 * A settled cycle where A (loaf 2, cherries 1, eggs 1) has a validated base, B (loaf 2) has a draft and K (cherries 1)
 * has a nothing-to-collect base, because the cherries and the eggs were received as 0. With a spare draft, C (loaf 1) also has a draft.
 *
 * @return array{cycle: string, reception: string, cherries: string, eggs: string, households: array<string, string>, baskets: array<string, string>, orders: array<string, string>, manager: User}
 */
function lateArrivalWeek(bool $withSpareDraft = false): array
{
    [$cycleId, $baker, $loaf] = cycleWithBaker();
    $product = function (string $name) use ($baker) {
        $id = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $id, 'producer_id' => $baker->id, 'name' => $name, 'unit' => 'unit', 'price_cents' => 500, 'variable' => false, 'active' => true]);

        return $id;
    };
    $cherries = $product('Cherries');
    $eggs = $product('Eggs');
    $households = [];
    $demand = ['A' => [$loaf => '2', $cherries => '1', $eggs => '1'], 'B' => [$loaf => '2'], 'K' => [$cherries => '1']];
    // A spare draft household keeps the cycle open while A and B are both validated.
    if ($withSpareDraft) {
        $demand['C'] = [$loaf => '1'];
    }
    foreach ($demand as $name => $lines) {
        $households[$name] = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $households[$name], 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
        foreach ($lines as $productId => $quantity) {
            test()->actingAs(memberOf($households[$name], "Member {$name}"))->put("/cycles/{$cycleId}/order/lines/{$productId}", ['quantity' => $quantity])->assertRedirect();
        }
        app('auth')->forgetGuards();
    }
    DB::table('cycle_producers')->where('cycle_id', $cycleId)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);
    $manager = accountWith(Role::Manager);
    $reception = "/manage/cycles/{$cycleId}/receptions/{$baker->id}";
    test()->actingAs($manager)->put("{$reception}/products/{$cherries}/received", ['received' => '0'])->assertRedirect();
    test()->put("{$reception}/products/{$eggs}/received", ['received' => '0'])->assertRedirect();
    test()->post("{$reception}/validate")->assertRedirect();
    test()->post("{$reception}/distribution")->assertRedirect();

    $orders = [];
    $baskets = [];
    foreach ($households as $name => $household) {
        $orders[$name] = (string) DB::table('orders')->where('cycle_id', $cycleId)->where('household_id', $household)->value('id');
        $baskets[$name] = (string) DB::table('baskets')->where('source_order_id', $orders[$name])->value('id');
    }
    test()->post("/finance/baskets/{$baskets['A']}/validation")->assertRedirect();

    return compact('cycleId', 'reception', 'cherries', 'eggs', 'households', 'baskets', 'orders', 'manager') + ['cycle' => $cycleId];
}
