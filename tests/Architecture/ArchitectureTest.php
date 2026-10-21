<?php

use Illuminate\Database\Eloquent\Model;

/*
 * Architecture rules A1-A8 of specs/constitution.md, one test per rule, each looping over every
 * bounded context found in src/. A9 (frontend boundaries) is enforced by Oxlint in vite.config.ts.
 */

arch('A1 domain is pure: only its own Domain, Shared\Domain and PHP itself', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        expect("Guixa\\{$context}\\Domain")->toOnlyUse([
            "Guixa\\{$context}\\Domain",
            'Guixa\Shared\Domain',
        ]);
    }
});

arch('A2 application depends on Domain only', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        expect("Guixa\\{$context}\\Application")->toOnlyUse([
            "Guixa\\{$context}\\Domain",
            "Guixa\\{$context}\\Application",
            'Guixa\Shared\Domain',
            'Guixa\Shared\Application',
        ]);
    }
});

arch('A3 dependencies point inwards: Domain and Application never use Infrastructure', function () {
    $infrastructure = array_map(
        fn (string $context) => "Guixa\\{$context}\\Infrastructure",
        architectureContexts(includeShared: true),
    );

    foreach (architectureContexts(includeShared: true) as $context) {
        foreach (['Domain', 'Application'] as $layer) {
            expect("Guixa\\{$context}\\{$layer}")->not->toUse($infrastructure);
        }
    }
});

arch('A4 a context never touches another context Domain or Infrastructure', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        foreach (architectureContexts() as $other) {
            if ($context === $other) {
                continue;
            }

            expect("Guixa\\{$context}")->not->toUse([
                "Guixa\\{$other}\\Domain",
                "Guixa\\{$other}\\Infrastructure",
            ]);
        }
    }
});

arch('A5 controllers use only Application and domain errors', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        expect('App\Http')
            ->not->toUse("Guixa\\{$context}\\Domain")
            ->ignoring("Guixa\\{$context}\\Domain\\Error");
        expect('App\Http')->not->toUse("Guixa\\{$context}\\Infrastructure");
    }
});

arch('A6 Eloquent models live in Infrastructure\Persistence', function () {
    $allowed = ['App\Models\User'];

    foreach (architectureContexts(includeShared: true) as $context) {
        $allowed[] = "Guixa\\{$context}\\Infrastructure\\Persistence";
    }

    foreach (['App', 'Guixa'] as $namespace) {
        expect($namespace)->not->toExtend(Model::class)->ignoring($allowed);
    }
});

arch('A7 use cases are final with a single public __invoke', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        foreach (architectureClasses("Guixa\\{$context}\\Application") as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod('__invoke')) {
                continue;
            }

            expect($reflection->isFinal())->toBeTrue("{$class} must be final");
            expect($reflection->getShortName())->toMatch('/^[A-Z][a-z0-9]+([A-Z][a-z0-9]*)+$/', "{$class} must be named <Verb><Noun>");

            $publicMethods = array_map(
                fn (ReflectionMethod $method) => $method->getName(),
                array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === $class
                        && $method->getName() !== '__construct',
                ),
            );

            expect(array_values($publicMethods))->toBe(['__invoke'], "{$class} must expose only __invoke");
        }
    }
});

arch('A7 value objects and DTOs are final readonly', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        foreach (architectureClasses("Guixa\\{$context}\\Application") as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->hasMethod('__invoke')) {
                continue;
            }

            expect($reflection->isFinal() && $reflection->isReadOnly())
                ->toBeTrue("{$class} is a DTO and must be final readonly");
        }

        foreach (architectureClasses("Guixa\\{$context}\\Domain\\ValueObject") as $class) {
            $reflection = new ReflectionClass($class);

            expect($reflection->isFinal() && $reflection->isReadOnly())
                ->toBeTrue("{$class} is a value object and must be final readonly");
        }
    }
});

arch('A7 repository ports are interfaces in Domain', function () {
    foreach (architectureContexts(includeShared: true) as $context) {
        foreach (architectureClasses("Guixa\\{$context}\\Domain", concreteOnly: false) as $class) {
            if (! str_ends_with($class, 'Repository')) {
                continue;
            }

            expect((new ReflectionClass($class))->isInterface())
                ->toBeTrue("{$class} must be an interface");
        }
    }
});

arch()->preset()->php();

arch()->preset()->security();

arch('A8 no debug helpers anywhere')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
