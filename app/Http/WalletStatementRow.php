<?php

namespace App\Http;

use Guixa\Wallet\Application\WalletStatement;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class WalletStatementRow
{
    /** @param list<WalletMovementRow> $movements newest first */
    public function __construct(
        public string $balance,
        public array $movements,
    ) {}

    public static function from(WalletStatement $statement): self
    {
        return new self(
            EuroAmount::format($statement->balanceCents),
            array_map(WalletMovementRow::from(...), $statement->movements),
        );
    }
}
