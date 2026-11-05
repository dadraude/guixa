<?php

use Guixa\Sales\Application\ListMemberPendingCollections;
use Guixa\Sales\Application\ListPendingCollections;
use Guixa\Sales\Application\ReadPendingCollections;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketState;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;
use Tests\Support\InMemory\Sales\InMemoryWeeklyBasketSource;

function pendingBasket(InMemoryBasketRepository $repository, InMemoryWeeklyBasketSource $source, string $id, string $household, string $validatedAt, ?string $cycle = null, bool $late = false, bool $validated = true): void
{
    $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
    $line = new BasketLine("line-{$id}", $id, 'honey', 'Honey', 'jar', 700, false, null, Quantity::fromString('1'), $created);
    if ($cycle !== null) {
        $source->place("order-{$id}", $cycle);
    }
    $draft = new Basket($id, $household, "Household {$household}", BasketState::Draft, 'manager', $created, [$line],
        kind: $cycle === null ? BasketKind::Stock : BasketKind::Weekly, sourceOrderId: $cycle === null ? null : "order-{$id}", late: $late);
    $repository->save($draft);
    if ($validated) {
        $repository->save($draft->validated('manager', new DateTimeImmutable($validatedAt)));
    }
}

it('reads pending collections once for every consumer: ordered, cycle-independent, drafts excluded and audiences scoped (R24, R24b, R35)', function () {
    $repository = new InMemoryBasketRepository;
    $source = new InMemoryWeeklyBasketSource;
    pendingBasket($repository, $source, 'c-late', 'mine', '2026-11-09T10:00:00+00:00', cycle: 'closed-cycle', late: true);
    pendingBasket($repository, $source, 'b-stock', 'mine', '2026-11-08T10:00:00+00:00');
    pendingBasket($repository, $source, 'a-stock', 'mine', '2026-11-08T10:00:00+00:00');
    pendingBasket($repository, $source, 'x-draft', 'mine', '', validated: false);
    pendingBasket($repository, $source, 'z-other', 'theirs', '2026-11-01T10:00:00+00:00', cycle: 'closed-cycle');

    $all = (new ReadPendingCollections($repository, $source))();
    expect(array_map(fn ($row) => $row->basketId, $all))->toBe(['z-other', 'a-stock', 'b-stock', 'c-late'])
        ->and(array_map(fn ($row) => $row->weekly, $all))->toBe([true, false, false, true])
        ->and(array_map(fn ($row) => $row->cycleId, $all))->toBe(['closed-cycle', null, null, 'closed-cycle'])
        ->and((new ReadPendingCollections($repository, $source))('mine'))->toHaveCount(3);

    expect(array_map(fn ($row) => $row->id, (new ListPendingCollections($repository, $source))(Actor::manager('manager'))))->toBe(['z-other', 'a-stock', 'b-stock', 'c-late'])
        ->and(array_map(fn ($row) => $row->id, (new ListPendingCollections($repository, $source))(Actor::manager('manager'), 'closed-cycle')))->toBe(['z-other', 'c-late'])
        ->and((new ListPendingCollections($repository, $source))(Actor::manager('manager'), 'unknown-cycle'))->toBe([])
        ->and(array_map(fn ($row) => $row->id, (new ListMemberPendingCollections($repository))(Actor::memberOf('member', 'mine'))))->toBe(['a-stock', 'b-stock', 'c-late'])
        ->and((new ListMemberPendingCollections($repository))(Actor::memberOf('member', 'empty-home')))->toBe([]);
    expect(fn () => (new ListPendingCollections($repository, $source))(Actor::memberOf('member', 'mine')))->toThrow(AuthorizationException::class)
        ->and(fn () => (new ListMemberPendingCollections($repository))(Actor::manager('manager')))->toThrow(AuthorizationException::class);
});
