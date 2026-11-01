<?php

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Wallet\Application\ChargeForBasket;
use Guixa\Wallet\Application\ChargeForPickup;
use Guixa\Wallet\Application\GetBalance;
use Guixa\Wallet\Application\GetWalletStatement;
use Guixa\Wallet\Application\ReadHouseholdBalance;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Guixa\Wallet\Domain\Error\BasketAlreadyCharged;
use Guixa\Wallet\Domain\Error\CorrectionNoteRequired;
use Guixa\Wallet\Domain\Error\InvalidCorrectionAmount;
use Guixa\Wallet\Domain\Error\InvalidTopUpAmount;
use Guixa\Wallet\Domain\Error\PickupAlreadyCharged;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\InMemoryMovementRepository;

beforeEach(function () {
    $this->movements = new InMemoryMovementRepository;
    $this->clock = new FrozenClock('2026-10-22 10:00:00');
    $this->manager = Actor::manager('manager-account');
    $this->household = 'household-h';
    $this->member = Actor::memberOf('anna-account', 'household-h');
    $this->topUp = new RecordTopUp($this->movements, $this->clock);
    $this->correct = new RecordCorrection($this->movements, $this->clock);
    $this->charge = new ChargeForPickup($this->movements, $this->clock);
    $this->balance = new GetBalance($this->movements);
    $this->statement = new GetWalletStatement($this->movements);
});

it('records a top-up and a correcting movement that bring the balance back to zero (S24, R29, R32)', function () {
    ($this->topUp)($this->manager, $this->household, 5000, 'transfer of the 3rd');
    $this->clock->travelTo('2026-10-22 11:00:00');
    ($this->correct)($this->manager, $this->household, -5000, 'duplicated');

    $statement = ($this->statement)($this->member, $this->household);

    expect($statement->balanceCents)->toBe(0)
        ->and(array_map(fn ($m) => [$m->type, $m->amountCents, $m->note], $statement->movements))
        ->toBe([['correction', -5000, 'duplicated'], ['top_up', 5000, 'transfer of the 3rd']]);
});

it('dates a movement when it is recorded and keeps top-ups out of any cycle (R29)', function () {
    ($this->topUp)($this->manager, $this->household, 1000);

    $movement = ($this->statement)($this->manager, $this->household)->movements[0];

    expect($movement->occurredAt)->toEqual($this->clock->now())
        ->and($movement->cycleId)->toBeNull()
        ->and($movement->note)->toBeNull();
});

it('rejects a top-up that is not positive (R29)', function (int $cents) {
    expect(fn () => ($this->topUp)($this->manager, $this->household, $cents))->toThrow(InvalidTopUpAmount::class);
    expect(($this->balance)($this->manager, $this->household))->toBe(0);
})->with([0, -100]);

it('rejects a correction of zero or without a note (R32)', function () {
    expect(fn () => ($this->correct)($this->manager, $this->household, 0, 'typo'))->toThrow(InvalidCorrectionAmount::class);
    expect(fn () => ($this->correct)($this->manager, $this->household, 100, '  '))->toThrow(CorrectionNoteRequired::class);
    expect(($this->statement)($this->manager, $this->household)->movements)->toBe([]);
});

it('records a correction in a cycle, positive or negative (R32)', function (int $cents) {
    ($this->correct)($this->manager, $this->household, $cents, 'recount', 'cycle-1');

    $movement = ($this->statement)($this->manager, $this->household)->movements[0];

    expect($movement->amountCents)->toBe($cents)->and($movement->cycleId)->toBe('cycle-1');
})->with([250, -250]);

it('lets the balance go negative with no limit (R30)', function () {
    ($this->topUp)($this->manager, $this->household, 500);
    ($this->charge)($this->manager, $this->household, 'cycle-1', 120000);

    expect(($this->balance)($this->manager, $this->household))->toBe(-119500);
});

it('charges a positive total, credits a negative one and records nothing for zero (R22, R30)', function () {
    ($this->charge)($this->manager, $this->household, 'cycle-1', 2240);
    ($this->charge)($this->manager, $this->household, 'cycle-2', -150);
    $nothing = ($this->charge)($this->manager, $this->household, 'cycle-3', 0);

    $movements = ($this->statement)($this->manager, $this->household)->movements;

    expect($nothing)->toBeNull()
        ->and(array_map(fn ($m) => [$m->type, $m->amountCents, $m->cycleId], $movements))
        ->toEqualCanonicalizing([['charge', -2240, 'cycle-1'], ['credit', 150, 'cycle-2']])
        ->and(($this->balance)($this->manager, $this->household))->toBe(-2090);
});

it('refuses a second charge or credit for the same household and cycle (R23)', function () {
    ($this->charge)($this->manager, $this->household, 'cycle-1', 2240);

    expect(fn () => ($this->charge)($this->manager, $this->household, 'cycle-1', -150))->toThrow(PickupAlreadyCharged::class);
    expect(($this->balance)($this->manager, $this->household))->toBe(-2240);
});

it('charges distinct baskets for one household while rejecting replay and preserving weekly charges (T55)', function () {
    $basketCharge = new ChargeForBasket($this->movements, $this->clock);
    $first = $basketCharge($this->manager, $this->household, 'basket-1', 300);
    $second = $basketCharge($this->manager, $this->household, 'basket-2', 200);
    ($this->charge)($this->manager, $this->household, 'cycle-1', 100);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and(($this->balance)($this->manager, $this->household))->toBe(-600)
        ->and(array_map(fn ($movement) => $movement->basketId, ($this->statement)($this->manager, $this->household)->movements))
        ->toEqualCanonicalizing(['basket-1', 'basket-2', null]);
    expect(fn () => $basketCharge($this->manager, 'other-household', 'basket-1', -50))->toThrow(BasketAlreadyCharged::class);
    expect(($this->balance)($this->manager, $this->household))->toBe(-600);
});

it('shows a member their household movements with their cycle, and nobody else\'s (R31)', function () {
    ($this->charge)($this->manager, $this->household, 'cycle-1', 700);
    ($this->topUp)($this->manager, 'household-j', 900);

    $statement = ($this->statement)($this->member, $this->household);

    expect($statement->balanceCents)->toBe(-700)
        ->and($statement->movements)->toHaveCount(1)
        ->and($statement->movements[0]->cycleId)->toBe('cycle-1');
    expect(fn () => ($this->statement)($this->member, 'household-j'))->toThrow(AuthorizationException::class);
});

it('keeps recording to managers and the balance query to managers (R29, R32)', function (Role $role) {
    $actor = new Actor('other-account', [$role], 'household-h');

    expect(fn () => ($this->topUp)($actor, $this->household, 100))->toThrow(AuthorizationException::class);
    expect(fn () => ($this->correct)($actor, $this->household, 100, 'x'))->toThrow(AuthorizationException::class);
    expect(fn () => ($this->charge)($actor, $this->household, 'cycle-1', 100))->toThrow(AuthorizationException::class);
    expect(fn () => ($this->balance)($actor, $this->household))->toThrow(AuthorizationException::class);
})->with([Role::Member, Role::Producer]);

it('refuses the statement to a producer and to a member of no household', function () {
    expect(fn () => ($this->statement)(Actor::producer('p', 'producer-1'), $this->household))->toThrow(AuthorizationException::class);
    expect(fn () => ($this->statement)(new Actor('a', [Role::Member]), $this->household))->toThrow(AuthorizationException::class);
});

it('lets a manager still top up a household without members (R35a)', function () {
    ($this->topUp)($this->manager, 'deactivated-household', 800, 'cash');

    expect(($this->balance)($this->manager, 'deactivated-household'))->toBe(800);
});

it('reads a household balance for another context without an actor (R30, R35a)', function () {
    ($this->topUp)($this->manager, $this->household, 500, 'cash');
    ($this->charge)($this->manager, $this->household, 'cycle-1', 1200);

    expect((new ReadHouseholdBalance($this->movements))($this->household))->toBe(-700)
        ->and((new ReadHouseholdBalance($this->movements))('household-j'))->toBe(0);
});

it('lets Finance read every household statement and record only an existing basket charge (R31, R36c, S84)', function () {
    $finance = new Actor('finance', [Role::Finance]);
    $basketCharge = new ChargeForBasket($this->movements, $this->clock);
    $basketCharge($finance, $this->household, 'basket-1', 1200);

    expect(($this->statement)($finance, $this->household)->balanceCents)->toBe(-1200)
        ->and(($this->balance)($finance, $this->household))->toBe(-1200)
        ->and(($this->statement)($finance, 'household-j')->balanceCents)->toBe(0);
    expect(fn () => ($this->topUp)($finance, $this->household, 100))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->correct)($finance, $this->household, 100, 'x'))->toThrow(AuthorizationException::class);
});
