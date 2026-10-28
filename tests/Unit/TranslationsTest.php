<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Symfony\Component\Finder\Finder;

it('resolves the same Catalan source keys and placeholders through Laravel', function () {
    $translator = new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'ca');
    $translator->setFallback('ca');

    expect($translator->get('Your order'))->toBe('La teva comanda')
        ->and($translator->get('Pickup on :date', ['date' => '30/10/2026']))->toBe('Recollida el 30/10/2026')
        ->and($translator->get('errors.ProducerClosed'))->toBe('El termini d’aquest productor ja s’ha tancat.');
});

it('translates the stock and return line errors to Catalan', function (string $error) {
    $translator = new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'ca');
    $translator->setFallback('ca');

    expect($translator->has("errors.{$error}"))->toBeTrue()
        ->and($translator->get("errors.{$error}"))->not->toBe("errors.{$error}");
})->with(['NotEnoughStock', 'StockProductInactive', 'InvalidLineQuantity', 'StockLineNotInPickup', 'ReturnLineNotInPickup', 'StockProductNotFound', 'ReturnItemNotFound']);

it('has a Catalan message for every domain error returned by the HTTP adapter', function () {
    $translator = new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'ca');
    $translator->setFallback('ca');

    $errors = Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/src')->path('Domain/Error');
    $missing = [];
    $checked = 0;

    foreach ($errors as $error) {
        $checked++;
        $key = 'errors.'.$error->getBasename('.php');
        if (! $translator->has($key)) {
            $missing[] = $key;
        }
    }

    expect($checked)->toBeGreaterThan(80)->and(array_unique($missing))->toBe([]);
});

it('has a Catalan catalog value for every literal PHP and Blade translation key', function () {
    $translator = new Translator(new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang'), 'ca');
    $translator->setFallback('ca');

    $root = dirname(__DIR__, 2);
    $files = Finder::create()->files()->name('*.php')->in([$root.'/app', $root.'/src', $root.'/bootstrap', $root.'/routes', $root.'/resources/views']);
    $missing = [];
    $checked = 0;

    foreach ($files as $file) {
        preg_match_all('/\b(?:__|trans)\(\s*(?:\'((?:\\\\.|[^\'])*)\'|"((?:\\\\.|[^"])*)")\s*[,)]/s', $file->getContents(), $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1] ?: $match[2];
            $checked++;
            if (! $translator->has(stripslashes($key))) {
                $missing[] = $file->getRelativePathname().': '.$key;
            }
        }
    }

    expect($checked)->toBeGreaterThan(10)->and($missing)->toBe([]);
});
