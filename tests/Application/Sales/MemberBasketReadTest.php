<?php

use Guixa\Sales\Application\GetMemberBasket;
use Guixa\Sales\Application\ListMemberBaskets;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

it('lists and reads only the actor current household from the public Sales queries (R19a, R35)', function () {
    $repository = new InMemoryBasketRepository;
    $now = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $own = new Basket('own', 'mine', 'Mine', BasketState::Draft, 'manager', $now);
    $other = new Basket('other', 'theirs', 'Theirs', BasketState::Draft, 'manager', $now);
    $repository->save($own);
    $repository->save($other);

    $actor = Actor::memberOf('member', 'mine');
    expect(array_map(fn ($row) => $row->id, (new ListMemberBaskets($repository))($actor)))->toBe(['own'])
        ->and((new GetMemberBasket($repository))($actor, 'own')->id)->toBe('own');
    expect(fn () => (new GetMemberBasket($repository))($actor, 'other'))->toThrow(BasketNotFound::class);
    expect(fn () => (new GetMemberBasket($repository))(Actor::memberOf('member', 'new-home'), 'own'))->toThrow(BasketNotFound::class);
    expect(fn () => (new ListMemberBaskets($repository))(Actor::producer('producer', 'farm')))->toThrow(AuthorizationException::class);
});
