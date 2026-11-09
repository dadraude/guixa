<?php

use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\ProportionalAllocator;
use Guixa\Ordering\Domain\ValueObject\ProportionalClaim;
use Guixa\Shared\Domain\Quantity;

$when = new DateTimeImmutable('2026-11-01');

it('asks an order for a line it does not have as a programming error', function () {
    expect(fn () => Order::start('o', 'c', 'h')->lineOf('missing'))->toThrow(LogicException::class, 'The order has no line of product missing.');
});

it('refuses a target that is not a multiple of the allocation step', function () use ($when) {
    $claims = [new ProportionalClaim('h1', Quantity::fromString('2'), $when, 'l1')];

    expect(fn () => (new ProportionalAllocator)->allocate(Quantity::fromString('1.500'), Quantity::fromString('1'), $claims))
        ->toThrow(InvalidArgumentException::class, 'Quantities must be multiples of the step.');
});
