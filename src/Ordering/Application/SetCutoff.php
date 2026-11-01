<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Exception;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\InvalidCutoff;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Error\InvalidLocalTime;
use Guixa\Shared\Domain\LocalTime;
use Illuminate\Support\Facades\Gate;

final class SetCutoff
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** @param  string  $at  ISO 8601; without an offset it is read in the cooperative's timezone */
    public function __invoke(Actor $actor, string $cycleId, string $producerId, string $at): void
    {
        Gate::forUser($actor)->authorize('manage');

        $now = $this->clock->now()->setTimezone($this->clock->timezone());
        $moment = $this->parse($at);

        $this->transactions->run(function () use ($cycleId, $producerId, $moment, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->setCutoff($producerId, $moment, $now);
            $this->cycles->add($cycle);
        });
    }

    private function parse(string $at): DateTimeImmutable
    {
        // PHP's own parser also accepts "tomorrow" or an empty string, which are not a moment a manager picked.
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?(Z|[+-]\d{2}:\d{2})?$/', $at, $parts)) {
            throw InvalidCutoff::malformed($at);
        }

        $timezone = $this->clock->timezone();

        if (($parts[4] ?? '') !== '') {
            return $this->parseWithOffset($at)->setTimezone($timezone);
        }

        if (($parts[3] ?? '') !== '' && $parts[3] !== ':00') {
            throw InvalidCutoff::malformed($at);
        }

        // Resolved like CutoffRule does, so a repeated local time means its first occurrence.
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $parts[1], $timezone);

        if ($date === false || $date->format('Y-m-d') !== $parts[1]) {
            throw InvalidCutoff::malformed($at);
        }

        try {
            return LocalTime::at($date, $parts[2], $timezone);
        } catch (InvalidLocalTime) {
            throw InvalidCutoff::malformed($at);
        }
    }

    private function parseWithOffset(string $at): DateTimeImmutable
    {
        try {
            $moment = new DateTimeImmutable($at);
        } catch (Exception) {
            throw InvalidCutoff::malformed($at);
        }

        // A date such as 2026-02-30 parses by rolling over, and is reported as a warning.
        if (DateTimeImmutable::getLastErrors() !== false) {
            throw InvalidCutoff::malformed($at);
        }

        return $moment;
    }
}
