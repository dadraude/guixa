<?php

namespace App\Http;

use Guixa\Ordering\Application\CycleData;
use Guixa\Ordering\Application\ParticipantData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A cycle as the cycles page shows it, its producers named and sorted by name. */
#[TypeScript]
final readonly class CycleRow
{
    /** @param  list<CycleParticipantRow>  $participants */
    public function __construct(
        public string $id,
        public string $pickupDate,
        public bool $cancelled,
        public array $participants,
    ) {}

    /** @param  array<string, string>  $producerNames  by producer id */
    public static function from(CycleData $cycle, array $producerNames): self
    {
        $participants = array_map(
            fn (ParticipantData $participant) => CycleParticipantRow::from($participant, $producerNames[$participant->producerId] ?? $participant->producerId),
            $cycle->participants,
        );
        usort($participants, fn (CycleParticipantRow $a, CycleParticipantRow $b) => mb_strtolower($a->producerName) <=> mb_strtolower($b->producerName));

        return new self($cycle->id, $cycle->pickupDate, $cycle->cancelled, $participants);
    }
}
