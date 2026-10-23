<?php

namespace Guixa\Membership\Application;

final readonly class ProducerAccountView
{
    public function __construct(
        public string $producerId,
        public string $email,
        public bool $invitationPending,
    ) {}
}
