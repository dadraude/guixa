<?php

namespace Guixa\Ordering\Application;

use DateTimeImmutable;
use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleAlreadyExists;
use Guixa\Ordering\Domain\Error\InvalidPickupDate;
use Guixa\Ordering\Domain\Error\PickupWeekdayAlreadySet;
use Guixa\Ordering\Domain\Error\PickupWeekdayNotSet;
use Guixa\Ordering\Domain\Error\ProducerNotActive;
use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class OpenCycle
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly CooperativeSettings $settings,
        private readonly ProducerCutoffRules $rules,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  string  $pickupDate  YYYY-MM-DD
     * @param  list<string>  $producerIds  the producers that participate from the start (R4a)
     * @param  ?int  $weekday  ISO day, only while the cooperative has none (R4c)
     * @param  bool  $permanent  with `$weekday`: save it as the cooperative's weekday; otherwise it applies to this cycle only
     * @return string the new cycle's id
     */
    public function __invoke(Actor $actor, string $pickupDate, array $producerIds, ?int $weekday = null, bool $permanent = false): string
    {
        Gate::forUser($actor)->authorize('manage');

        $given = $weekday === null ? null : PickupWeekday::of($weekday);
        $effective = $this->effectiveWeekday($given);

        $now = $this->clock->now()->setTimezone($this->clock->timezone());
        $cycle = Cycle::open($this->cycles->nextId(), $this->parse($pickupDate, $now), $effective, $now);

        if ($this->cycles->existsOnPickupDate($cycle->pickupDate())) {
            throw CycleAlreadyExists::forDate($pickupDate);
        }

        foreach ($producerIds as $producerId) {
            $rule = $this->rules->activeRuleOf($producerId) ?? throw ProducerNotActive::withId($producerId);
            $cycle->addParticipant($producerId, $rule, $now);
        }

        $this->transactions->run(function () use ($cycle, $given, $permanent) {
            $this->cycles->add($cycle);

            if ($given !== null && $permanent) {
                $this->settings->changePickupWeekday($given);
            }
        });

        return $cycle->id;
    }

    /** The cooperative's weekday, or the one given when it has none; giving one when it has one is refused (R4c). */
    private function effectiveWeekday(?PickupWeekday $given): PickupWeekday
    {
        $current = $this->settings->pickupWeekday();

        if ($current !== null && $given !== null) {
            throw PickupWeekdayAlreadySet::create();
        }

        return $current ?? $given ?? throw PickupWeekdayNotSet::create();
    }

    private function parse(string $pickupDate, DateTimeImmutable $now): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $pickupDate, $now->getTimezone());

        if ($parsed === false || $parsed->format('Y-m-d') !== $pickupDate) {
            throw InvalidPickupDate::malformed($pickupDate);
        }

        return $parsed;
    }
}
