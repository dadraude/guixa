<?php

use Guixa\Catalog\Application\ReadProducerNames;
use Tests\Support\Catalog\CatalogWorld;

it('reads the names of several producers at once, leaving unknown ones out', function () {
    $world = new CatalogWorld;
    $baker = ($world->createProducer)($world->manager, 'Baker', 3, '20:00');
    $grower = ($world->createProducer)($world->manager, 'Grower', 2, '12:00');
    ($world->createProducer)($world->manager, 'Other', 2, '12:00');

    $names = (new ReadProducerNames($world->producers))([$baker, $grower, '00000000-0000-7000-8000-0000000000ff']);

    expect($names)->toBe([$baker => 'Baker', $grower => 'Grower']);
});
