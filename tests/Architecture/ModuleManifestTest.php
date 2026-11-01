<?php

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Tests\Support\Architecture\ModuleContract;

it('A11 declares exactly the implemented modules and valid integration exports', function () {
    $root = dirname(__DIR__, 2);
    $manifest = require $root.'/architecture/modules.php';
    expect(array_keys($manifest['modules']))->toEqualCanonicalizing(architectureContexts(includeShared: true));

    foreach ($manifest['modules'] as $module => $contract) {
        expect($contract['owns'])->toBeString()->not->toBeEmpty();
        expect($contract['dependencies'])->not->toContain($module);
        foreach ($contract['dependencies'] as $dependency) {
            expect($manifest['modules'])->toHaveKey($dependency);
        }
        foreach ($contract['exports'] as $symbol => $callers) {
            expect($symbol)->toStartWith($module === 'Shared' && str_starts_with($symbol, 'Domain\\') ? 'Domain\\' : 'Application\\');
            $name = "Guixa\\{$module}\\{$symbol}";
            expect(class_exists($name) || interface_exists($name) || enum_exists($name))->toBeTrue("Export {$name} must exist");
            expect($callers)->not->toBeEmpty();
            foreach ($callers as $caller) {
                expect($manifest['modules'])->toHaveKey($caller);
                expect($manifest['modules'][$caller]['dependencies'])->toContain($module);
            }
        }
    }
});

it('A11 and A12 runtime sources respect module exports, dependencies and table ownership', function () {
    $root = dirname(__DIR__, 2);
    $checker = new ModuleContract(require $root.'/architecture/modules.php');
    $violations = [];
    foreach (['src', 'app', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $path = substr($file->getPathname(), strlen($root) + 1);
                $violations = [...$violations, ...$checker->violations($path, file_get_contents($file->getPathname()))];
            }
        }
    }
    expect($violations)->toBe([], implode("\n", $violations));
});

it('A12 assigns every migration-created table exactly one valid owner', function () {
    $root = dirname(__DIR__, 2);
    $manifest = require $root.'/architecture/modules.php';
    $tables = [];
    foreach (glob($root.'/database/migrations/*.php') as $path) {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse(file_get_contents($path)) ?? [];
        $nodes = (new NodeTraverser(new NameResolver))->traverse($nodes);
        foreach ((new NodeFinder)->findInstanceOf($nodes, Node\Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name && $call->class->toString() === 'Illuminate\\Support\\Facades\\Schema'
                && $call->name instanceof Node\Identifier && $call->name->toString() === 'create') {
                $table = $call->getArgs()[0]->value;
                expect($table)->toBeInstanceOf(Node\Scalar\String_::class);
                $tables[] = $table->value;
            }
        }
    }
    expect($tables)->toEqualCanonicalizing(array_keys($manifest['tables']));
    foreach ($manifest['tables'] as $owner) {
        expect([...array_keys($manifest['modules']), 'Platform'])->toContain($owner);
    }
});

it('A12 exceptions have exact existing paths, reasons and still-required scope', function () {
    $root = dirname(__DIR__, 2);
    $manifest = require $root.'/architecture/modules.php';
    foreach ($manifest['table_exceptions'] as $path => $exception) {
        expect(is_file($root.'/'.$path))->toBeTrue();
        expect($exception['reason'])->not->toBeEmpty();
        expect(['read', 'write'])->toContain($exception['access']);
        $withoutException = $manifest;
        unset($withoutException['table_exceptions'][$path]);
        $issues = implode("\n", (new ModuleContract($withoutException))->violations($path, file_get_contents($root.'/'.$path)));
        foreach ($exception['tables'] as $table) {
            expect($manifest['tables'])->toHaveKey($table);
            expect($issues)->toContain("{$table} belongs to");
        }
    }
    foreach ($manifest['dynamic_tables'] as $path => $methods) {
        $withoutException = $manifest;
        unset($withoutException['dynamic_tables'][$path]);
        expect(implode("\n", (new ModuleContract($withoutException))->violations($path, file_get_contents($root.'/'.$path))))
            ->toContain('dynamic table access requires a reviewed declaration');
        $class = 'Guixa\\'.str_replace('/', '\\', substr($path, 4, -4));
        foreach ($methods as $name => $exception) {
            $method = new ReflectionMethod($class, $name);
            expect($method->isPrivate())->toBeTrue();
            expect(array_map(fn (ReflectionParameter $parameter) => $parameter->name, $method->getParameters()))->toContain($exception['parameter']);
            expect($exception['reason'])->not->toBeEmpty();
        }
    }
    foreach ($manifest['sql_exceptions'] as $path => $statements) {
        foreach ($statements as $sql => $reason) {
            expect($reason)->not->toBeEmpty();
            $withoutException = $manifest;
            unset($withoutException['sql_exceptions'][$path][$sql]);
            expect(implode("\n", (new ModuleContract($withoutException))->violations($path, file_get_contents($root.'/'.$path))))
                ->toContain('raw SQL requires a reviewed declaration');
        }
    }
});
