<?php

/*
 * Rules A13-A17 of specs/constitution.md (ADR 0001), one test per rule. Each rule scans source text,
 * like A10, and compares its offenders with the adoption baseline in
 * architecture/presentation-exceptions.php: a new offender fails, and so does a listed file that no
 * longer offends, so the baseline can only shrink.
 */

const FRONTEND_EXTENSIONS = ['vue', 'ts'];

arch('A13 Vue shows allowed actions instead of comparing states', function () {
    $offenders = presentationOffenders(
        ['resources/js/pages', 'resources/js/components'],
        FRONTEND_EXTENSIONS,
        fn (string $source) => preg_match('/\.(state|status|kind)\s*[!=]==/', $source) === 1,
    );

    expectBaseline('A13', $offenders);
});

arch('A14 the frontend does no business arithmetic on amounts', function () {
    $offenders = presentationOffenders(
        ['resources/js/pages', 'resources/js/components', 'resources/js/lib', 'resources/js/composables'],
        FRONTEND_EXTENSIONS,
        fn (string $source) => preg_match('/\w+Cents\s*[-+*\/%]\s|[-+*\/%]\s*\w+Cents\b/', $source) === 1,
    );

    expectBaseline('A14', $offenders);
});

arch('A15 Application DTOs carry values and do not compute with Money or Quantity', function () {
    $offenders = presentationOffenders(
        array_map(fn (string $context) => "src/{$context}/Application", architectureContexts(includeShared: true)),
        ['php'],
        fn (string $source) => ! str_contains($source, 'function __invoke')
            && preg_match('/use Guixa\\\\Shared\\\\Domain\\\\(Money|Quantity);/', $source) === 1,
    );

    expectBaseline('A15', $offenders);
});

arch('A16 controllers do not filter or aggregate use case results', function () {
    $offenders = presentationOffenders(
        ['app/Http/Controllers'],
        ['php'],
        fn (string $source) => preg_match('/\b(array_filter|array_reduce|array_sum|usort)\s*\(|->(filter|reject|sum|sortBy)\s*\(/', $source) === 1,
    );

    expectBaseline('A16', $offenders);
});

arch('A17 states and kinds are enums, not compared string literals', function () {
    $offenders = presentationOffenders(
        ['src'],
        ['php'],
        fn (string $source) => preg_match('/\b(state|status|kind)\s*[!=]==\s*\'/', $source) === 1,
    );

    expectBaseline('A17', $offenders);
});

/**
 * Repository-relative paths under the given directories whose source matches the rule.
 *
 * @param  list<string>  $directories
 * @param  list<string>  $extensions
 * @param  callable(string): bool  $offends
 * @return list<string>
 */
function presentationOffenders(array $directories, array $extensions, callable $offends): array
{
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach ($directories as $directory) {
        if (! is_dir("{$root}/{$directory}")) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! in_array($file->getExtension(), $extensions, true) || str_ends_with($file->getFilename(), '.spec.ts')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                throw new RuntimeException("Cannot read {$file->getPathname()}");
            }

            if ($offends($source)) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }

    sort($offenders);

    return $offenders;
}

/** @param list<string> $offenders */
function expectBaseline(string $rule, array $offenders): void
{
    $baseline = (require dirname(__DIR__, 2).'/architecture/presentation-exceptions.php')[$rule];

    expect(array_values(array_diff($offenders, $baseline)))
        ->toBe([], "{$rule}: new offenders; fix them instead of adding them to the baseline");
    expect(array_values(array_diff($baseline, $offenders)))
        ->toBe([], "{$rule}: these files no longer offend; remove them from architecture/presentation-exceptions.php");
}
