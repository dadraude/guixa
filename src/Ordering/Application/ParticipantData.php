<?php

namespace Guixa\Ordering\Application;

final readonly class ParticipantData
{
    /** @param  ?string  $cutoff  ISO 8601 with the cooperative's offset, null when there is none */
    public function __construct(
        public string $producerId,
        public bool $participating,
        public ?string $cutoff,
    ) {}
}
