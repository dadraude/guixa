<?php

use Guixa\Sales\Application\BasketActions;
use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Shared\Domain\Quantity;

function actionsBasket(BasketState $state = BasketState::Draft, BasketKind $kind = BasketKind::Stock): Basket
{
    $when = new DateTimeImmutable('2026-11-01');
    $line = new BasketLine('line', 'basket', 'oil', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when);

    return new Basket('basket', 'household', 'Household K', $state, 'manager', $when, [$line], kind: $kind);
}

it('lets staff edit, validate and cancel an independent draft', function () {
    $can = BasketActions::forStaff(actionsBasket());

    expect([$can->edit, $can->validate, $can->cancel, $can->markCollected, $can->correct, $can->correctCollection])
        ->toBe([true, true, true, false, false, false]);
});

it('keeps a weekly draft from being cancelled', function () {
    $can = BasketActions::forStaff(actionsBasket(kind: BasketKind::Weekly));

    expect($can->edit)->toBeTrue()->and($can->cancel)->toBeFalse();
});

it('lets staff collect and correct the amount of a validated basket but not correct a collection', function () {
    $can = BasketActions::forStaff(actionsBasket(BasketState::Validated));

    expect([$can->edit, $can->markCollected, $can->correct, $can->correctCollection])
        ->toBe([false, true, true, false]);
});

it('lets staff correct the collection of a collected basket only', function () {
    $can = BasketActions::forStaff(actionsBasket(BasketState::Collected));

    expect([$can->markCollected, $can->correct, $can->correctCollection])->toBe([false, true, true]);
});

it('gives a member nothing but collecting a validated basket', function () {
    $validated = BasketActions::forMember(actionsBasket(BasketState::Validated));
    $draft = BasketActions::forMember(actionsBasket());

    expect([$validated->markCollected, $validated->edit, $validated->correct, $validated->correctCollection])
        ->toBe([true, false, false, false])
        ->and([$draft->markCollected, $draft->edit, $draft->validate, $draft->cancel])->toBe([false, false, false, false]);
});

it('reports the status shown to users, with the state for everything but a reversed basket', function () {
    expect(BasketData::from(actionsBasket(BasketState::NothingToCollect))->status)->toBe('nothing_to_collect')
        ->and(BasketData::from(actionsBasket(BasketState::Collected))->status)->toBe('collected')
        ->and(BasketData::from(actionsBasket(BasketState::Validated))->status)->toBe('validated')
        ->and(BasketData::from(actionsBasket(BasketState::Validated), forStaff: false)->can->markCollected)->toBeTrue();
});
