<?php

use Guixa\Membership\Application\CanLogIn;
use Guixa\Membership\Application\GetWalletHousehold;
use Guixa\Membership\Application\GrantFinanceRole;
use Guixa\Membership\Application\RemoveFinanceRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Error\AccountDeactivated;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryAccountRepository;
use Tests\Support\InMemory\InMemoryHouseholdRepository;

it('grants and removes independent Finance access on an existing account while preserving other roles (R36a, R36c, S84)', function () {
    $accounts = new InMemoryAccountRepository;
    $households = new InMemoryHouseholdRepository;
    $manager = Actor::manager('manager');
    $account = Account::restore('finance', 'Fin', Email::from('fin@example.org'), [Role::Producer], null, 'producer', new DateTimeImmutable, null);
    $accounts->save($account);
    $grant = new GrantFinanceRole($accounts, $households, new ImmediateTransactionRunner);
    $remove = new RemoveFinanceRole($accounts, $households, new ImmediateTransactionRunner);

    $grant($manager, 'finance');
    $grant($manager, 'finance');
    expect($accounts->get('finance')->roles)->toEqual([Role::Producer, Role::Finance]);
    $remove($manager, 'finance');
    expect($accounts->get('finance')->roles)->toEqual([Role::Producer]);
    expect(fn () => $grant(Actor::memberOf('member', 'H'), 'finance'))->toThrow(AuthorizationException::class);
    expect(fn () => $grant($manager, 'missing'))->toThrow(AccountNotFound::class);
});

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
    $account = Account::restore('finance', 'Fin', Email::from('fin@example.org'), [], null, null, new DateTimeImmutable, null);
    $accounts->save($account);
    $grant = new GrantFinanceRole($accounts, $households, new ImmediateTransactionRunner);
    $remove = new RemoveFinanceRole($accounts, $households, new ImmediateTransactionRunner);
    $login = new CanLogIn($accounts);

    expect($login('finance'))->toBeFalse();
    $grant(Actor::manager('manager'), 'finance');
    expect($login('finance'))->toBeTrue();
    $remove(Actor::manager('manager'), 'finance');
    expect($login('finance'))->toBeFalse();
});

it('does not grant Finance on a deactivated account (R36b, R36c)', function () {
    $accounts = new InMemoryAccountRepository;
    $households = new InMemoryHouseholdRepository;
    $account = Account::restore('inactive', 'Fin', Email::from('fin@example.org'), [], null, null, new DateTimeImmutable, new DateTimeImmutable);
    $accounts->save($account);
    $grant = new GrantFinanceRole($accounts, $households, new ImmediateTransactionRunner);

    expect(fn () => $grant(Actor::manager('manager'), 'inactive'))->toThrow(AccountDeactivated::class);
});
