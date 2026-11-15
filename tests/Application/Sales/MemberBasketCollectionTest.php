<?php

use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

function memberCollectionFixture(): array
{
    $baskets = new InMemoryBasketRepository;
    $when = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    foreach (['own' => 'mine', 'other' => 'theirs'] as $id => $household) {
        $line = new BasketLine("line-{$id}", $id, 'product', 'Oil', 'bottle', 1200, false, null, Quantity::fromString('1'), $when);
        $baskets->save((new Basket($id, $household, $household, BasketState::Draft, 'manager', $when, [$line]))->validated('manager', $when));
    }

    return [$baskets, fn (string $now) => new MarkBasketCollected($baskets, new ImmediateTransactionRunner, new FrozenClock($now))];
}

it('lets a current member collect their own household basket keeping the first actor and time (R23b, S26, S82)', function () {
    [$baskets, $collect] = memberCollectionFixture();

    $collect('2026-11-04 09:00:00')(Actor::memberOf('anna', 'mine'), 'own');
    $collect('2026-11-05 09:00:00')(Actor::memberOf('berta', 'mine'), 'own');

    $basket = $baskets->get('own');
    expect($basket->state)->toBe(BasketState::Collected)
        ->and($basket->collectedBy)->toBe('anna')
        ->and($basket->collectedAt->format('Y-m-d H:i'))->toBe('2026-11-04 09:00')
        ->and($basket->collectionEvents)->toHaveCount(1);
});

it('rejects a member collecting another household basket and leaves it pending (R23b, R35, S82)', function () {
    [$baskets, $collect] = memberCollectionFixture();

    expect(fn () => $collect('2026-11-04 09:00:00')(Actor::memberOf('anna', 'mine'), 'other'))
        ->toThrow(AuthorizationException::class)
        ->and($baskets->get('other')->state)->toBe(BasketState::Validated);
    expect(fn () => $collect('2026-11-04 09:00:00')(Actor::producer('farm', 'farm-1'), 'own'))
        ->toThrow(AuthorizationException::class);
});

it('keeps staff collection working and refuses a member collecting a draft (R23b)', function () {
    [$baskets, $collect] = memberCollectionFixture();
    $collect('2026-11-04 09:00:00')(Actor::manager('boss'), 'other');
    expect($baskets->get('other')->collectedBy)->toBe('boss');

    $draft = new Basket('draft', 'mine', 'Mine', BasketState::Draft, 'manager', new DateTimeImmutable('2026-10-30'));
    $baskets->save($draft);
    expect(fn () => $collect('2026-11-04 09:00:00')(Actor::memberOf('anna', 'mine'), 'draft'))
        ->toThrow(BasketNotEditable::class);
});
