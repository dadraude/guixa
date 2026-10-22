<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\Error\ProducerAlreadyHasAccount;
use Guixa\Membership\Domain\Error\UnknownProducer;
use Guixa\Membership\Domain\ProducerDirectory;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class InviteProducerAccount
{
    public function __construct(
        private readonly ProducerDirectory $producers,
        private readonly AccountRepository $accounts,
        private readonly SendInvitation $sendInvitation,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @return string the new account's id */
    public function __invoke(Actor $actor, string $producerId, string $name, string $email): string
    {
        $actor->assertManager();

        if (! $this->producers->exists($producerId)) {
            throw UnknownProducer::withId($producerId);
        }

        if ($this->accounts->producerHasAccount($producerId)) {
            throw ProducerAlreadyHasAccount::for($producerId);
        }

        $email = Email::from($email);

        if ($this->accounts->emailIsTaken($email)) {
            throw EmailAlreadyInUse::for($email->value);
        }

        $account = Account::inviteProducer($this->accounts->nextId(), $name, $email, $producerId);

        $this->transactions->run(function () use ($account): void {
            $this->accounts->save($account);
            ($this->sendInvitation)($account);
        });

        return $account->id;
    }
}
