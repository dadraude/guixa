<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

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
