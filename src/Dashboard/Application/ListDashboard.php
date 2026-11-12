<?php

namespace Guixa\Dashboard\Application;

use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\OpportunityKind;
use Guixa\Dashboard\Domain\OrderOpportunitiesRepository;
use Guixa\Dashboard\Domain\ReceptionWorkRepository;
use Guixa\Dashboard\Domain\SendWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\BasketWork;
use Guixa\Dashboard\Domain\ValueObject\ReceptionWork;
use Guixa\Dashboard\Domain\ValueObject\SendWork;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

/** The signed-in account's pending work, read from current persisted data; it changes nothing (R1, R3). */
final class ListDashboard
{
    /**
     * The words the page renders for a group's context, copied from lang/ca.json ("Pickup :date", "Cycle", "No cycle"). Application
     * cannot use the translator (architecture A2), so ordering by the displayed label keeps its own copy here; the Catalan default is
     * the only locale, and AttentionPriorityTest fails if lang/ca.json stops matching these words.
     */
    private const array CONTEXT_WORDS = ['pickup' => 'recollida del', 'cycle' => 'cicle', 'noCycle' => 'cap cicle'];

    private const array ACCENT_FOLD = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', 'l·l' => 'll',
    ];

    public function __construct(
        private readonly BasketWorkRepository $basketWork,
        private readonly ReceptionWorkRepository $receptionWork,
        private readonly SendWorkRepository $sendWork,
        private readonly OrderOpportunitiesRepository $opportunities,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor): DashboardData
    {
        $gate = Gate::forUser($actor);
        $isStaff = $gate->allows('manage-sales');
        $isMember = $actor->householdId !== null && $gate->allows('memberOf', ['household', $actor->householdId]);

        // A member who is also staff has the same basket in both scopes: it is one action on one record (R2).
        $records = [
            ...($isMember ? $this->basketWork->memberCollection($actor->householdId) : []),
            ...($isStaff ? $this->basketWork->staffCollection() : []),
            ...($isStaff ? $this->basketWork->staffDrafts() : []),
        ];

        $groups = [
            ...$this->basketGroups($records, $isStaff),
            ...($gate->allows('manage') ? $this->sendGroups() : []),
            ...$this->receptionGroups($gate->allows('receive'), $gate->allows('settle-reception')),
        ];
        usort($groups, $this->comparePriority(...));

        // Read last: the time is stamped only once every source read has succeeded; a failed read raises ReadUnavailable instead (R15).
        $opportunities = $this->opportunities($actor, $isMember);

        $hasContext = $this->hasContext($actor, $isMember);

        // Both flags are decided only here, after every read succeeded, so a failed read can never report caught up (R14, R15).
        return new DashboardData($groups, $opportunities, $this->clock->now(), $hasContext && $groups === [], ! $hasContext);
    }

    /** Whether the account has any household, producer or staff context to be caught up in (R14). */
    private function hasContext(Actor $actor, bool $isMember): bool
    {
        $gate = Gate::forUser($actor);

        return $isMember
            || $gate->allows('manage-sales') || $gate->allows('manage') || $gate->allows('receive') || $gate->allows('view-producers')
            || ($gate->allows('producer') && $gate->allows('owns', ['producer', $actor->producerId]));
    }

    /**
     * No pending family carries an actual deadline today, so priority is the scheduled date (undated last), then the four-value
     * stage, then the displayed context, then the action code, then the stable identifiers, which a rename cannot change (R12, R13).
     */
    private function comparePriority(AttentionGroupData $a, AttentionGroupData $b): int
    {
        return [$a->scheduledDate === null, $a->scheduledDate, ActionKind::from($a->action)->stage()] <=> [$b->scheduledDate === null, $b->scheduledDate, ActionKind::from($b->action)->stage()]
            ?: strcmp($this->contextOrderKey($a), $this->contextOrderKey($b))
            ?: [$a->action, $a->cycleId ?? '', $a->key] <=> [$b->action, $b->cycleId ?? '', $b->key];
    }

    /**
     * The label the page shows for the group, in the same shape and with the same Catalan words (see CONTEXT_WORDS): the producer
     * (when the group is one reception or send) before the pickup date, or the cycle / no-cycle context when it has no date. A group
     * of several producers shows only the date part. Accents and case are folded so "àlex" sorts with "alex" for Catalan names,
     * without depending on ext-intl. The date is the ISO date: only same-date groups reach this comparison, so it orders consistently.
     */
    private function contextOrderKey(AttentionGroupData $group): string
    {
        $context = $group->scheduledDate !== null
            ? self::CONTEXT_WORDS['pickup'].' '.$group->scheduledDate
            : self::CONTEXT_WORDS[$group->cycleId === null ? 'noCycle' : 'cycle'];
        $label = $group->producerName === null ? $context : "{$group->producerName} · {$context}";

        return strtr(mb_strtolower($label), self::ACCENT_FOLD);
    }

    /**
     * Where the signed-in account can act now without any evidence of unfinished work: gated on the current household, producer and role (R11).
     *
     * @return list<OpportunityData>
     */
    private function opportunities(Actor $actor, bool $isMember): array
    {
        $gate = Gate::forUser($actor);
        $opportunities = [];
        if ($isMember) {
            foreach ($this->opportunities->openWindows() as $window) {
                $opportunities[] = new OpportunityData(OpportunityKind::MemberOrder->value.":cycle:{$window->cycleId}", OpportunityKind::MemberOrder->value, OpportunityArea::MemberOrder, $window->cycleId, $window->scheduledDate, $window->cutoffs);
            }
        }
        if ($gate->allows('producer') && $gate->allows('owns', ['producer', $actor->producerId])) {
            foreach ($this->opportunities->sentSummaries($actor->producerId) as $summary) {
                $opportunities[] = new OpportunityData(OpportunityKind::ProducerSummary->value.":cycle:{$summary->cycleId}", OpportunityKind::ProducerSummary->value, OpportunityArea::ProducerCycle, $summary->cycleId, $summary->scheduledDate);
            }
        }
        if ($gate->allows('view-producers')) {
            $opportunities[] = new OpportunityData(OpportunityKind::CatalogueMaintenance->value, OpportunityKind::CatalogueMaintenance->value, OpportunityArea::CatalogueProducers, null, null);
        }
        usort($opportunities, fn (OpportunityData $a, OpportunityData $b) => [OpportunityKind::from($a->kind)->rank(), $a->scheduledDate === null, $a->scheduledDate, $a->key]
            <=> [OpportunityKind::from($b->kind)->rank(), $b->scheduledDate === null, $b->scheduledDate, $b->key]);

        return $opportunities;
    }

    /** @return list<AttentionGroupData> one group per cycle: the manager sends from the cycles list, where the send control lives */
    private function sendGroups(): array
    {
        $byCycle = [];
        foreach ($this->sendWork->pending() as $work) {
            $byCycle[$work->cycleId]["{$work->cycleId}|{$work->producerId}"] = $work;
        }

        return array_map(function (array $members, string $cycleId) {
            /** @var SendWork $first */
            $first = reset($members);

            return new AttentionGroupData(
                ActionKind::SendOrder->value.":cycle:{$cycleId}",
                ActionKind::SendOrder->value,
                $cycleId,
                $first->scheduledDate,
                count($members),
                new DestinationData(false, null, DestinationArea::Cycles),
                count($members) === 1 ? $first->producerName : null,
            );
        }, $byCycle, array_keys($byCycle));
    }

    /** @return list<AttentionGroupData> */
    private function receptionGroups(bool $canReceive, bool $canSettle): array
    {
        if (! $canReceive) {
            return [];
        }

        $byGroup = [];
        foreach ($this->receptionWork->pending() as $work) {
            if ($work->action === ActionKind::MarkDistributed && ! $canSettle) {
                continue;
            }
            $byGroup[$this->receptionGroupKey($work)][$work->recordKey()] = $work;
        }

        return array_map(fn (array $members, string $key) => $this->receptionGroup($key, $members), $byGroup, array_keys($byGroup));
    }

    /** @param  non-empty-array<string, ReceptionWork>  $members */
    private function receptionGroup(string $key, array $members): AttentionGroupData
    {
        $first = reset($members);
        $single = count($members) === 1;

        return new AttentionGroupData(
            $key,
            $first->action->value,
            $first->cycleId,
            $first->scheduledDate,
            count($members),
            new DestinationData(false, null, DestinationArea::Receptions, $single ? $first->producerId : null),
            $single ? $first->producerName : null,
            count(array_filter($members, fn (ReceptionWork $work) => $work->allocationDifference)),
        );
    }

    private function receptionGroupKey(ReceptionWork $work): string
    {
        return "{$work->action->value}:cycle:{$work->cycleId}";
    }

    /**
     * @param  list<BasketWork>  $records
     * @return list<AttentionGroupData>
     */
    private function basketGroups(array $records, bool $staffWide): array
    {
        $byGroup = [];
        foreach ($records as $record) {
            $byGroup[$this->groupKey($record)][$record->action->value.':'.$record->basketId] = $record;
        }

        $groups = array_map(function (array $members, string $key) use ($staffWide) {
            $first = reset($members);

            return new AttentionGroupData(
                $key,
                $first->action->value,
                $first->cycleId,
                $first->scheduledDate,
                count($members),
                new DestinationData($staffWide, count($members) === 1 ? $first->basketId : null, collectionList: $first->action === ActionKind::CollectBasket),
            );
        }, $byGroup, array_keys($byGroup));

        return $groups;
    }

    private function groupKey(BasketWork $record): string
    {
        return $record->action->value.':'.($record->cycleId === null ? 'no-cycle' : "cycle:{$record->cycleId}");
    }
}
