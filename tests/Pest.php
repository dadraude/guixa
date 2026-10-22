<?php

use App\Models\User;
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
