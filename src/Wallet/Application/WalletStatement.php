<?php

namespace Guixa\Wallet\Application;

final readonly class WalletStatement
{
    /** @param list<MovementData> $movements newest first */
    public function __construct(
        public int $balanceCents,
        public array $movements,
    ) {}
}
