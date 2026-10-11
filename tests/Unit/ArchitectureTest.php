<?php

declare(strict_types=1);

use App\Actions\DeactivateMember;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/*
 * Project rules that a reviewer would otherwise have to check by eye.
 * When a review finding keeps coming back, it belongs here.
 */

arch('no debugging leftovers or unsafe PHP functions')
    ->preset()->php();

arch('no insecure functions')
    ->preset()->security();

arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('models use UUIDv7 primary keys (ADR 0005)')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(HasUuids::class);

arch('actions expose a handle method')
    ->expect('App\Actions')
    ->classes()
    ->toHaveMethod('handle');

arch('domain code does not depend on the HTTP layer')
    ->expect(['App\Actions', 'App\Auth', 'App\Models', 'App\Mail'])
    ->not->toUse(['App\Http', 'Illuminate\Http']);

arch('domain code does not depend on console, providers or Inertia (DDD)')
    ->expect(['App\Actions', 'App\Auth', 'App\Models'])
    ->not->toUse(['App\Console', 'App\Providers', 'Inertia']);

arch('entities do not depend on use cases or mail (DDD)')
    ->expect('App\Models')
    ->not->toUse(['App\Actions', 'App\Mail']);

arch('mail does not depend on use cases, so there are no layer cycles (DDD)')
    ->expect('App\Mail')
    ->not->toUse('App\Actions');

arch('classes stay small enough to have one responsibility (SRP)')
    ->expect(['App\Actions', 'App\Auth', 'App\Models', 'App\Mail'])
    ->classes()
    ->toHaveLineCountLessThan(150);

arch('the commission_member pivot is only read by CommissionMemberships')
    ->expect('App\\Auth\\CommissionMemberships')
    ->toOnlyBeUsedIn('App\\Auth\\MemberRoles');

arch('mails are queued after the transaction commits')
    ->expect('App\Mail')
    ->toImplement(ShouldQueueAfterCommit::class);

arch('env() is only read from config files')
    ->expect('env')
    ->not->toBeUsedIn(['App', 'Database']);

// Pest cannot match method names, so this scans the source: unused magic links are
// revoked through MagicLinkBroker::revokeUnused, never by querying the relation (DRA-60 review).
test('actions do not query magic links directly', function () {
    $files = (new Finder)->files()->in(dirname(__DIR__, 2).'/app/Actions')->name('*.php');

    $offenders = [];
    foreach ($files as $file) {
        if (Str::contains($file->getContents(), 'magicLinks(')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

// Providers are the composition root and legitimately use the container (Gate definitions,
// Fortify callbacks). Everywhere else dependencies are injected, not located (DRA-71 review).
arch('app code injects its dependencies instead of locating them with app() or resolve()')
    ->expect('App')
    ->not->toUse(['app', 'resolve', 'Illuminate\Support\Facades\App', 'Illuminate\Container\Container'])
    ->ignoring('App\Providers');

// Pest cannot match method names, so the HTTP layer is guarded by two checks:
// 1. the arch rule rejects the classes a transaction starts from (DB facade and its
//    global alias, the connection and the database manager);
// 2. the source scan below rejects the calls that need no such class, such as
//    Member::query()->lockForUpdate(), $model->getConnection()->transaction() or the
//    global alias \DB::transaction().
arch('transactions do not start from the HTTP layer')
    ->expect('App\Http')
    ->not->toUse([
        DB::class,
        ConnectionInterface::class,
        DatabaseManager::class,
    ]);

test('the HTTP layer holds no transactions or locks, they live in actions', function () {
    $forbidden = ['lockForUpdate', 'sharedLock', '->transaction(', '::transaction('];

    $files = (new Finder)->files()->in(dirname(__DIR__, 2).'/app/Http')->name('*.php');

    $offenders = [];
    foreach ($files as $file) {
        if (Str::contains($file->getContents(), $forbidden)) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * Inspect typed controller parameters and their calls, so a renamed request
 * variable or an aliased import cannot bypass the rule.
 *
 * @return list<string>
 */
function controllerRequestViolations(string $source): array
{
    $nodes = (new ParserFactory)->createForHostVersion()->parse($source) ?? [];
    $traverser = new NodeTraverser;
    $traverser->addVisitor(new NameResolver);
    $nodes = $traverser->traverse($nodes);
    $finder = new NodeFinder;
    $violations = [];

    foreach ($finder->findInstanceOf($nodes, ClassMethod::class) as $method) {
        $formRequests = [];
        $deactivators = [];

        foreach ($method->params as $param) {
            if (! $param->type instanceof Name || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $type = $param->type->toString();
            if (is_subclass_of($type, FormRequest::class)) {
                $formRequests[] = $param->var->name;
            }
            if ($type === DeactivateMember::class) {
                $deactivators[] = $param->var->name;
            }
        }

        foreach ($finder->findInstanceOf($method->stmts ?? [], MethodCall::class) as $call) {
            if (! $call->name instanceof Identifier || ! $call->var instanceof Variable || ! is_string($call->var->name)) {
                continue;
            }

            $name = $call->name->toString();
            $variable = $call->var->name;
            if (in_array($variable, $deactivators, true) && $name === 'handle') {
                $violations[] = 'DeactivateMember::handle at line '.$call->getStartLine();
            }
            if (in_array($variable, $formRequests, true) && in_array($name, ['string', 'input', 'integer', 'boolean', 'get'], true)) {
                $violations[] = 'Raw FormRequest '.$name.' at line '.$call->getStartLine();
            }
        }

        foreach ($finder->findInstanceOf($method->stmts ?? [], StaticCall::class) as $call) {
            if ($call->class instanceof Name && $call->name instanceof Identifier
                && $call->class->toString() === DeactivateMember::class
                && $call->name->toString() === 'handle') {
                $violations[] = 'DeactivateMember::handle at line '.$call->getStartLine();
            }
        }
    }

    // Constructor promotion and typed properties are the usual way an action
    // becomes available to another controller method.
    foreach ($finder->findInstanceOf($nodes, Class_::class) as $class) {
        $deactivatorProperties = [];

        foreach ($class->getProperties() as $property) {
            if ($property->type instanceof Name && $property->type->toString() === DeactivateMember::class) {
                foreach ($property->props as $prop) {
                    $deactivatorProperties[] = $prop->name->toString();
                }
            }
        }

        foreach ($class->getMethod('__construct')?->params ?? [] as $param) {
            if ($param->flags !== 0 && $param->type instanceof Name
                && $param->type->toString() === DeactivateMember::class
                && $param->var instanceof Variable && is_string($param->var->name)) {
                $deactivatorProperties[] = $param->var->name;
            }
        }

        foreach ($class->getMethods() as $method) {
            foreach ($finder->findInstanceOf($method->stmts ?? [], MethodCall::class) as $call) {
                if ($call->name instanceof Identifier && $call->name->toString() === 'handle'
                    && $call->var instanceof PropertyFetch
                    && $call->var->var instanceof Variable && $call->var->var->name === 'this'
                    && $call->var->name instanceof Identifier
                    && in_array($call->var->name->toString(), $deactivatorProperties, true)) {
                    $violations[] = 'DeactivateMember::handle at line '.$call->getStartLine();
                }
            }
        }
    }

    return $violations;
}

test('controllers never call DeactivateMember handle or read raw FormRequest input', function () {
    $files = Finder::create()->files()->in(dirname(__DIR__, 2).'/app/Http/Controllers')->name('*.php');
    $offenders = [];

    foreach ($files as $file) {
        foreach (controllerRequestViolations($file->getContents()) as $violation) {
            $offenders[] = $file->getRelativePathname().': '.$violation;
        }
    }

    expect($offenders)->toBe([]);
});

test('the controller rules catch deliberate violations and allow validated data', function () {
    $bad = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Actions\DeactivateMember as Deactivator;
use App\Http\Requests\Settings\PasswordUpdateRequest as PasswordRequest;
class ExampleController {
    public function __construct(private Deactivator $deactivator) {}
    public function update(PasswordRequest $data, Deactivator $action): void {
        $data->string('password');
        $action->handle(null);
        Deactivator::handle(null);
        $this->deactivator->handle(null);
    }
}
PHP;
    $good = str_replace(['$'.'data->string', '$'.'action->handle(null);', 'Deactivator::handle(null);', '$'.'this->deactivator->handle(null);'], ['$'.'data->safe()->string', '$'.'action->handleAs(null);', '', '$'.'this->deactivator->handleAs(null);'], $bad);
    $typedProperty = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Actions\DeactivateMember as Deactivator;
class ExampleController {
    private Deactivator $action;
    public function update(): void { $this->action->handle(null); }
}
PHP;

    expect(controllerRequestViolations($bad))->toHaveCount(4)
        ->and(controllerRequestViolations($typedProperty))->toHaveCount(1)
        ->and(controllerRequestViolations($good))->toBe([]);
});
