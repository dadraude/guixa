<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\Error\ManagerAlreadyExists;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\TransactionRunner;

/** Installation use case (no actor: nobody can log in yet); rejected once any manager exists (R36a, D17). */
final class CreateFirstManager
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly SendInvitation $sendInvitation,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @return string the new account's id */
    public function __invoke(string $name, string $email): string
    {
        $email = Email::from($email);

        return $this->transactions->run(function () use ($name, $email): string {
            if ($this->accounts->hasManager()) {
                throw ManagerAlreadyExists::create();
            }

            if ($this->accounts->emailIsTaken($email)) {
                throw EmailAlreadyInUse::for($email->value);
            }

            $account = Account::inviteManager($this->accounts->nextId(), $name, $email);

            $this->accounts->save($account);
            ($this->sendInvitation)($account);

            return $account->id;
        });
    }
}
