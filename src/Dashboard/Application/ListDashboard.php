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
use Illuminate\Support\Facades\Gate;

/** The signed-in account's pending work, read from current persisted data; it changes nothing (R1, R3). */
final class ListDashboard
{
    public function __construct(
        private readonly BasketWorkRepository $basketWork,
        private readonly ReceptionWorkRepository $receptionWork,
        private readonly SendWorkRepository $sendWork,
        private readonly OrderOpportunitiesRepository $opportunities,
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
        usort($groups, fn (AttentionGroupData $a, AttentionGroupData $b) => [$a->scheduledDate === null, $a->scheduledDate, ActionKind::from($a->action)->rank(), $a->key]
            <=> [$b->scheduledDate === null, $b->scheduledDate, ActionKind::from($b->action)->rank(), $b->key]);

        return new DashboardData($groups, $this->opportunities($actor, $isMember));
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
