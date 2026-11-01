<?php

use Guixa\Catalog\Domain\Product;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

it('keeps broad permissions tied to the exact role combinations', function () {
    $manager = Actor::manager('manager');
    $finance = new Actor('finance', [Role::Finance]);
    $member = Actor::memberOf('member', 'h1');
    $producer = Actor::producer('producer', 'p1');

    foreach ([$manager, $finance, $member, $producer] as $actor) {
        expect(Gate::forUser($actor)->allows('manage'))->toBe($actor === $manager)
            ->and(Gate::forUser($actor)->allows('manage-sales'))->toBe($actor === $manager || $actor === $finance)
            ->and(Gate::forUser($actor)->allows('manage-wallet'))->toBe($actor === $manager || $actor === $finance)
            ->and(Gate::forUser($actor)->allows('view-stock-offers'))->toBe($actor === $manager || $actor === $member);
    }
});

it('requires a real household link for membership and preserves collection overrides', function () {
    $member = Actor::memberOf('member', 'h1');
    $manager = Actor::manager('manager');
    $finance = new Actor('finance', [Role::Finance]);
    $malformed = new Actor('malformed', [Role::Member, Role::Finance]);

    expect(Gate::forUser($member)->allows('memberOf', ['household', 'h1']))->toBeTrue()
        ->and(Gate::forUser($member)->allows('memberOf', ['household', 'h2']))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('memberOf', ['household', 'h1']))->toBeFalse()
        ->and(Gate::forUser($malformed)->allows('memberOf', ['household', 'h1']))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('collectFor', ['household', 'h2']))->toBeTrue()
        ->and(Gate::forUser($finance)->allows('collectFor', ['household', 'h2']))->toBeTrue()
        ->and(Gate::forUser($member)->allows('collectFor', ['household', 'h2']))->toBeFalse();
});

it('requires the producer role and matching ID even for combined or malformed actors', function () {
    $owner = new Actor('owner', [Role::Manager, Role::Producer], producerId: 'p1');
    $managerWithId = new Actor('manager', [Role::Manager], producerId: 'p1');
    $producerWithoutId = new Actor('producer', [Role::Producer]);

    expect(Gate::forUser($owner)->allows('owns', ['producer', 'p1']))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('owns', ['producer', 'p2']))->toBeFalse()
        ->and(Gate::forUser($managerWithId)->allows('owns', ['producer', 'p1']))->toBeFalse()
        ->and(Gate::forUser($producerWithoutId)->allows('producer'))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('producer'))->toBeTrue();
});

it('limits weekly product edits to their producer and stock deletion to managers', function () {
    $weekly = Product::create('weekly', 'p1', 'Bread', 'unit', Money::ofCents(100), false, null);
    $stock = Product::create('stock', 'p1', 'Bread', 'unit', Money::ofCents(100), false, null, true);
    $owner = Actor::producer('owner', 'p1');
    $other = Actor::producer('other', 'p2');
    $manager = Actor::manager('manager');

    expect(Gate::forUser($owner)->allows('update', $weekly))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $stock))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $weekly))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('update', $weekly))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $weekly))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $stock))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('delete', $stock))->toBeTrue()
        ->and(fn () => Gate::forUser($other)->authorize('delete', $weekly))->toThrow(AuthorizationException::class);
});
