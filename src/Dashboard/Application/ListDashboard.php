<?php

namespace Guixa\Dashboard\Application;

use Guixa\Dashboard\Domain\ActionKind;
use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\ValueObject\BasketWork;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The signed-in account's pending work, read from current persisted data; it changes nothing (R1, R3). */
final class ListDashboard
{
    public function __construct(private readonly BasketWorkRepository $basketWork) {}

    public function __invoke(Actor $actor): DashboardData
    {
        $gate = Gate::forUser($actor);
        $isStaff = $gate->allows('manage-sales');
        $isMember = $actor->householdId !== null && $gate->allows('memberOf', ['household', $actor->householdId]);

        // A member who is also staff has the same basket in both scopes: it is one action on one record (R2).
        $records = [
            ...($isMember ? $this->basketWork->memberCollection($actor->householdId) : []),
            ...($isStaff ? $this->basketWork->staffCollection() : []),
        ];

        return new DashboardData($this->collectionGroups($records, $isStaff));
    }

    /**
     * @param  list<BasketWork>  $records
     * @return list<AttentionGroupData>
     */
    private function collectionGroups(array $records, bool $staffWide): array
    {
        $byGroup = [];
        foreach ($records as $record) {
            $byGroup[$this->groupKey($record)][$record->basketId] = $record;
        }

        $groups = array_map(function (array $members, string $key) use ($staffWide) {
            $first = reset($members);

            return new AttentionGroupData(
                $key,
                ActionKind::CollectBasket->value,
                $first->cycleId,
                $first->scheduledDate,
                count($members),
                new DestinationData($staffWide, count($members) === 1 ? $first->basketId : null),
            );
        }, $byGroup, array_keys($byGroup));

        usort($groups, fn (AttentionGroupData $a, AttentionGroupData $b) => [$a->scheduledDate === null, $a->scheduledDate, $a->key]
            <=> [$b->scheduledDate === null, $b->scheduledDate, $b->key]);

        return $groups;
    }

    private function groupKey(BasketWork $record): string
    {
        return ActionKind::CollectBasket->value.':'.($record->cycleId === null ? 'no-cycle' : "cycle:{$record->cycleId}");
    }
}
