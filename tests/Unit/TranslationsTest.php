<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * A key missing from lang/ca.json silently falls back to its English source
 * text, so the UI would show English without any test failing.
 */

/**
 * @return list<string>
 */
function translationKeysUsedIn(string $directory, string $function, string $filePattern): array
{
    $pattern = '/\b'.preg_quote($function, '/').'\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
    $keys = [];

    foreach (Finder::create()->files()->in($directory)->name($filePattern) as $file) {
        preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $keys[] = stripslashes($match[2] ?? '' ?: $match[1]);
        }
    }

    return $keys;
}

/**
 * Dotted keys such as `validation.required` live in lang/ca/*.php, not in ca.json.
 */
function isJsonTranslationKey(string $key): bool
{
    return preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $key) !== 1;
}

test('every translated string has a Catalan translation', function () {
    $root = dirname(__DIR__, 2);
    $catalan = json_decode((string) file_get_contents($root.'/lang/ca.json'), true, flags: JSON_THROW_ON_ERROR);

    $usedKeys = [
        ...translationKeysUsedIn($root.'/app', '__', '*.php'),
        ...translationKeysUsedIn($root.'/routes', '__', '*.php'),
        ...translationKeysUsedIn($root.'/resources/views', '__', '*.php'),
        ...translationKeysUsedIn($root.'/resources/js', 't', '/\.(vue|ts)$/'),
    ];

    $missing = array_values(array_unique(array_filter(
        $usedKeys,
        fn (string $key) => isJsonTranslationKey($key) && ! array_key_exists($key, $catalan),
    )));

    expect($missing)->toBe([]);
});

test('every key in ca.json is used somewhere in the source', function () {
    $root = dirname(__DIR__, 2);
    $catalan = json_decode((string) file_get_contents($root.'/lang/ca.json'), true, flags: JSON_THROW_ON_ERROR);

    $source = '';
    foreach (['app', 'routes', 'resources', 'database', 'config'] as $directory) {
        foreach (Finder::create()->files()->in($root.'/'.$directory)->name('/\.(php|vue|ts)$/') as $file) {
            $source .= $file->getContents()."\n";
        }
    }

    // Used by Laravel's own mail views, which live in vendor/.
    $usedByFramework = ['All rights reserved.'];

    $unused = array_values(array_filter(
        array_keys($catalan),
        fn (string $key) => ! in_array($key, $usedByFramework, true)
            && ! str_contains($source, $key)
            && ! str_contains($source, addcslashes($key, "'")),
    ));

    expect($unused)->toBe([]);
});
