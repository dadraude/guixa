<?php

use Guixa\Membership\Application\CanLogIn;
use Guixa\Membership\Application\GetWalletHousehold;
use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Application\RemoveStaffRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Error\AccountDeactivated;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryAccountRepository;
use Tests\Support\InMemory\InMemoryHouseholdRepository;
use Tests\Support\InMemory\InMemoryRoleChangeLog;

it('lets Finance read only the household identity needed for wallet statements (R31, R36c)', function () {
    $households = new InMemoryHouseholdRepository;
    $households->save(Household::create('H', 'Home H'));
    $query = new GetWalletHousehold($households);
    $finance = new Actor('finance', [Role::Finance]);

    expect($query($finance, 'H')->name)->toBe('Home H');
    expect(fn () => $query(Actor::memberOf('member', 'H'), 'H'))->toThrow(AuthorizationException::class);
});

it('makes an activated unlinked account eligible to log in only while Finance remains granted (R36c, S84)', function () {
    $accounts = new InMemoryAccountRepository;
    $households = new InMemoryHouseholdRepository;
    $accounts->save(Account::restore('finance', 'Fin', Email::from('fin@example.org'), [], null, null, new DateTimeImmutable, null));
    $clock = new FrozenClock('2026-11-09 10:00:00');
    $grant = new GrantStaffRole($accounts, $households, new InMemoryRoleChangeLog, new ImmediateTransactionRunner, $clock);
    $remove = new RemoveStaffRole($accounts, $households, new InMemoryRoleChangeLog, new ImmediateTransactionRunner, $clock);
    $login = new CanLogIn($accounts);

    expect($login('finance'))->toBeFalse();
    $grant(Actor::manager('manager'), 'finance', 'finance');
    expect($login('finance'))->toBeTrue();
    $remove(Actor::manager('manager'), 'finance', 'finance');
    expect($login('finance'))->toBeFalse();
});

it('does not grant Finance on a deactivated account (R36b, R36c)', function () {
    $accounts = new InMemoryAccountRepository;
    $accounts->save(Account::restore('inactive', 'Fin', Email::from('fin@example.org'), [], null, null, new DateTimeImmutable, new DateTimeImmutable));
    $grant = new GrantStaffRole($accounts, new InMemoryHouseholdRepository, new InMemoryRoleChangeLog, new ImmediateTransactionRunner, new FrozenClock('2026-11-09 10:00:00'));

    expect(fn () => $grant(Actor::manager('manager'), 'inactive', 'finance'))->toThrow(AccountDeactivated::class);
});
