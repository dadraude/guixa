<?php

namespace App\Http\Controllers;

use App\Http\AttentionGroupRow;
use App\Http\OpportunityRow;
use App\Http\RoleAreas;
use Guixa\Dashboard\Application\AttentionGroupData;
use Guixa\Dashboard\Application\DashboardData;
use Guixa\Dashboard\Application\DestinationArea;
use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Dashboard\Application\OpportunityArea;
use Guixa\Dashboard\Application\OpportunityData;
use Guixa\Dashboard\Domain\Error\ReadUnavailable;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Actor $actor, ListDashboard $listDashboard): Response
    {
        $dashboard = $this->read($listDashboard, $actor);

        return Inertia::render('Dashboard', [
            'roles' => $actor->roleNames(),
            'isManager' => in_array('manager', $actor->roleNames(), true),
            'isMember' => in_array('member', $actor->roleNames(), true),
            // A known failed read sends no sections and no time, so the page cannot read it as zero pending work (R15).
            'readFailed' => $dashboard === null,
            'lastUpdatedAt' => $dashboard?->readAt->format(DATE_ATOM),
            'attention' => $dashboard === null ? null : array_map($this->attentionRow(...), $dashboard->attention),
            'opportunities' => $dashboard === null ? null : array_map($this->opportunityRow(...), $dashboard->opportunities),
            'can' => ['showCaughtUp' => $dashboard?->showCaughtUp ?? false, 'showNoContext' => $dashboard?->showNoContext ?? false],
            'areaShortcuts' => RoleAreas::shortcuts($actor),
        ]);
    }

    private function read(ListDashboard $listDashboard, Actor $actor): ?DashboardData
    {
        try {
            return $listDashboard($actor);
        } catch (ReadUnavailable) {
            return null;
        }
    }

    private function attentionRow(AttentionGroupData $group): AttentionGroupRow
    {
        return new AttentionGroupRow($group->key, $group->action, $group->cycleId, $group->scheduledDate, $group->count, $this->destinationUrl($group), $group->producerName, $group->allocationDifferenceCount);
    }

    private function opportunityRow(OpportunityData $opportunity): OpportunityRow
    {
        $cutoffs = array_map(fn (array $cutoff) => ['producerName' => $cutoff[0], 'closesAt' => $cutoff[1]], $opportunity->cutoffs);

        return new OpportunityRow($opportunity->key, $opportunity->kind, $opportunity->scheduledDate, $this->opportunityUrl($opportunity), $cutoffs);
    }

    private function opportunityUrl(OpportunityData $opportunity): string
    {
        return match ($opportunity->area) {
            OpportunityArea::MemberOrder => route('orders.edit', $opportunity->cycleId, false),
            OpportunityArea::ProducerCycle => route('producer.cycles.show', $opportunity->cycleId, false),
            OpportunityArea::CatalogueProducers => route('catalogue.producers.index', [], false),
        };
    }

    private function destinationUrl(AttentionGroupData $group): string
    {
        if ($group->destination->area === DestinationArea::Receptions) {
            $producerId = $group->destination->producerId;

            return $producerId === null ? route('receptions.index', [], false) : route('receptions.show', [$group->cycleId, $producerId], false);
        }

        if ($group->destination->area === DestinationArea::Cycles) {
            return route('manage.cycles.index', [], false);
        }

        $basketId = $group->destination->basketId;
        if ($group->destination->staffWide) {
            if ($basketId !== null) {
                return route('finance.baskets.show', $basketId, false);
            }

            if (! $group->destination->collectionList) {
                return route('finance.baskets.index', [], false);
            }

            return route('finance.baskets.index', $group->cycleId === null ? ['pending' => 1] : ['pending' => 1, 'cycle' => $group->cycleId], false);
        }

        return $basketId === null ? route('baskets.index', ['pending' => 1], false) : route('baskets.show', $basketId, false);
    }
}
