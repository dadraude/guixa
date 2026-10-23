<?php

namespace App\Http;

use Guixa\Ordering\Application\ParticipantData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A producer in a cycle, with the moment it closes (ISO 8601 in the cooperative's timezone) or none, and whether members can order from it now. */
#[TypeScript]
final readonly class CycleParticipantRow
{
    public function __construct(
        public string $producerId,
        public string $producerName,
        public bool $participating,
        public ?string $cutoff,
        public bool $open,
    ) {}

    public static function from(ParticipantData $participant, string $producerName): self
    {
        return new self($participant->producerId, $producerName, $participant->participating, $participant->cutoff, $participant->open);
    }
}
