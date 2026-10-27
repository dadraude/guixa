<?php

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Guixa\Stock\Application\DeactivateReturnItem;
use Guixa\Stock\Application\ListReturnItems;
use Guixa\Stock\Application\ReactivateReturnItem;
use Guixa\Stock\Application\UpdateReturnItem;
use Guixa\Stock\Domain\Error\InvalidReturnAmount;
use Guixa\Stock\Domain\Error\NameRequired;
use Guixa\Stock\Domain\Error\ReturnItemNotFound;
use Tests\Support\InMemory\InMemoryReturnItemRepository;

beforeEach(function () {
    $this->items = new InMemoryReturnItemRepository;
    $this->manager = Actor::manager('manager-account');
    $this->create = new CreateReturnItem($this->items);
    $this->update = new UpdateReturnItem($this->items);
    $this->deactivate = new DeactivateReturnItem($this->items);
    $this->reactivate = new ReactivateReturnItem($this->items);
    $this->list = new ListReturnItems($this->items);
});

it('creates a return item with its amount (R25)', function () {
    $id = ($this->create)($this->manager, 'Glass jar', 50);

    $item = ($this->list)($this->manager)[0];

    expect($item->id)->toBe($id)
        ->and($item->name)->toBe('Glass jar')
        ->and($item->amountCents)->toBe(50)
        ->and($item->active)->toBeTrue();
});

it('rejects an amount of zero or less (R25)', function (int $cents) {
    expect(fn () => ($this->create)($this->manager, 'Glass jar', $cents))->toThrow(InvalidReturnAmount::class);
    expect(($this->list)($this->manager))->toBe([]);
})->with([0, -50]);

it('requires a name (R25)', function () {
    expect(fn () => ($this->create)($this->manager, ' ', 50))->toThrow(NameRequired::class);
});

it('edits a return item with the same rules (R25)', function () {
    $id = ($this->create)($this->manager, 'Glass jar', 50);

    ($this->update)($this->manager, $id, 'Big glass jar', 75);

    $item = ($this->list)($this->manager)[0];
    expect($item->name)->toBe('Big glass jar')->and($item->amountCents)->toBe(75);

    expect(fn () => ($this->update)($this->manager, $id, 'Big glass jar', 0))->toThrow(InvalidReturnAmount::class);
    expect(($this->list)($this->manager)[0]->amountCents)->toBe(75);
});

it('deactivates a return item and keeps it listed as inactive (R25)', function () {
    $id = ($this->create)($this->manager, 'Glass jar', 50);

    ($this->deactivate)($this->manager, $id);

    expect(($this->list)($this->manager)[0]->active)->toBeFalse();
});

it('reactivates a return item without changing its amount (R25)', function () {
    $id = ($this->create)($this->manager, 'Glass jar', 50);
    ($this->deactivate)($this->manager, $id);

    ($this->reactivate)($this->manager, $id);

    expect(($this->list)($this->manager)[0]->active)->toBeTrue();
    expect(($this->list)($this->manager)[0]->amountCents)->toBe(50);
});

it('fails on an unknown return item', function () {
    expect(fn () => ($this->update)($this->manager, 'missing', 'Jar', 50))->toThrow(ReturnItemNotFound::class);
    expect(fn () => ($this->deactivate)($this->manager, 'missing'))->toThrow(ReturnItemNotFound::class);
    expect(fn () => ($this->reactivate)($this->manager, 'missing'))->toThrow(ReturnItemNotFound::class);
});

it('only lets a manager manage return items (R25, R36a)', function (Role $role) {
    $actor = new Actor('other-account', [$role], $role === Role::Member ? 'h1' : null);
    $id = ($this->create)($this->manager, 'Glass jar', 50);

    expect(fn () => ($this->create)($actor, 'Crate', 100))->toThrow(NotAllowed::class);
    expect(fn () => ($this->update)($actor, $id, 'Crate', 100))->toThrow(NotAllowed::class);
    expect(fn () => ($this->deactivate)($actor, $id))->toThrow(NotAllowed::class);
    expect(fn () => ($this->reactivate)($actor, $id))->toThrow(NotAllowed::class);
    expect(fn () => ($this->list)($actor))->toThrow(NotAllowed::class);
})->with([Role::Member, Role::Producer]);
