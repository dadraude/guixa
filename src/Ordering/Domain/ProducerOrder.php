<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;

/** A producer's part of a cycle: whether it participates and when it closes (R4a, R5). */
final class ProducerOrder
{
    private function __construct(
        public readonly string $producerId,
        private bool $participating,
        private ?DateTimeImmutable $cutoff,
    ) {}

    /** A producer that starts participating; a null cutoff means its moment had already passed (R5). */
    public static function participate(string $producerId, ?DateTimeImmutable $cutoff): self
    {
        return new self($producerId, true, $cutoff);
    }

    public static function restore(string $producerId, bool $participating, ?DateTimeImmutable $cutoff): self
    {
        return new self($producerId, $participating, $cutoff);
    }

    public function isParticipating(): bool
    {
        return $this->participating;
    }

    public function stopParticipating(): void
    {
        $this->participating = false;
    }

    /** The cutoff is kept as it was: the producer does not get a new one (R5). */
    public function participateAgain(): void
    {
        $this->participating = true;
    }

    public function changeCutoff(?DateTimeImmutable $cutoff): void
    {
        $this->cutoff = $cutoff;
    }

    public function cutoff(): ?DateTimeImmutable
    {
        return $this->cutoff;
    }
}
