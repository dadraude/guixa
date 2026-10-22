<?php

namespace App\Http;

use Guixa\Catalog\Application\ProducerData;
use Guixa\Membership\Application\ProducerAccountView;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A producer as the admin page shows it, with its default cutoff. */
#[TypeScript]
final readonly class ProducerRow
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public int $cutoffDaysBefore,
        public string $cutoffTime,
        public ?string $accountEmail,
        public bool $accountInvitationPending,
    ) {}

    public static function from(ProducerData $producer, ?ProducerAccountView $account): self
    {
        return new self(
            $producer->id,
            $producer->name,
            $producer->active,
            $producer->cutoffDaysBefore,
            $producer->cutoffTime,
            $account?->email,
            $account !== null && $account->invitationPending,
        );
    }
}
