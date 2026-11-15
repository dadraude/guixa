<?php

namespace Guixa\Ordering\Application;

final readonly class ParticipantData
{
    /** @param  ?string  $cutoff  ISO 8601 with the cooperative's offset, null when there is none; `$open`: members can order from this producer now (R6a); `$sent`: its order was sent; `$canSendManually`: closed, participating, unsent and with lines to send, since the scheduler sends the empty ones (R12a, D10) */
    public function __construct(
        public string $producerId,
        public bool $participating,
        public ?string $cutoff,
        public bool $open,
        public bool $sent = false,
        public bool $canSendManually = false,
    ) {}
}
