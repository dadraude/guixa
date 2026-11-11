<?php

namespace App\Http\Controllers;

use App\Http\AttentionGroupRow;
use App\Http\RoleAreas;
use Guixa\Catalog\Application\CountProducers;
use Guixa\Dashboard\Application\AttentionGroupData;
use Guixa\Dashboard\Application\DestinationArea;
use Guixa\Dashboard\Application\ListDashboard;
use Guixa\Membership\Application\CountAccounts;
use Guixa\Membership\Application\CountHouseholds;
use Guixa\Ordering\Application\CountCycles;
use Guixa\Ordering\Application\CountOpenMemberCycles;
use Guixa\Ordering\Application\ListMemberCycles;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\ReadHouseholdBalance;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(
        Actor $actor,
        ListMemberCycles $listMemberCycles,
        CountOpenMemberCycles $countOpenMemberCycles,
        CountCycles $countCycles,
        CountHouseholds $countHouseholds,
        CountAccounts $countAccounts,
        CountProducers $countProducers,
        ReadHouseholdBalance $readHouseholdBalance,
        ListDashboard $listDashboard,
    ): Response {
        $roles = $actor->roleNames();
        $isManager = in_array('manager', $roles, true);
        $isMember = in_array('member', $roles, true);

        $hasHousehold = $isMember && $actor->householdId !== null;
        $memberCycles = $hasHousehold ? $listMemberCycles($actor) : [];
        $nextPickup = null;
        foreach ($memberCycles as $cycle) {
            if (! $cycle->cancelled) {
                $nextPickup = $cycle->pickupDate;
                break;
            }
        }

        $balance = null;
        if ($hasHousehold) {
            $balance = $readHouseholdBalance($actor->householdId);
        }

        $managerSummary = null;
        if ($isManager) {
            $cycles = $countCycles($actor);
            $households = $countHouseholds($actor);
            $accounts = $countAccounts($actor);
            $producers = $countProducers($actor);

            $managerSummary = [
                'cycles' => $cycles->total,
                'activeCycles' => $cycles->active,
                'households' => $households->total,
                'activeHouseholds' => $households->active,
                'accounts' => $accounts->total,
                'pendingInvitations' => $accounts->pendingInvitations,
                'producers' => $producers->total,
                'activeProducers' => $producers->active,
            ];
        }

        return Inertia::render('Dashboard', [
            'roles' => $roles,
            'isManager' => $isManager,
            'isMember' => $isMember,
            'nextPickup' => $nextPickup,
            'openCycles' => $hasHousehold ? $countOpenMemberCycles($actor) : 0,
            'balance' => $balance,
            'attention' => array_map($this->attentionRow(...), $listDashboard($actor)->attention),
            'managerSummary' => $managerSummary,
            'areaShortcuts' => RoleAreas::shortcuts($actor),
        ]);
    }

    private function attentionRow(AttentionGroupData $group): AttentionGroupRow
    {
        return new AttentionGroupRow($group->key, $group->action, $group->cycleId, $group->scheduledDate, $group->count, $this->destinationUrl($group), $group->producerName, $group->allocationDifferenceCount);
    }

    private function destinationUrl(AttentionGroupData $group): string
    {
        if ($group->destination->area === DestinationArea::Receptions) {
            $producerId = $group->destination->producerId;

            return $producerId === null ? route('receptions.index', [], false) : route('receptions.show', [$group->cycleId, $producerId], false);
        }

        $basketId = $group->destination->basketId;
        if ($group->destination->staffWide) {
            if ($basketId !== null) {
                return route('finance.baskets.show', $basketId, false);
            }

            return route('finance.baskets.index', $group->cycleId === null ? ['pending' => 1] : ['pending' => 1, 'cycle' => $group->cycleId], false);
        }

        return $basketId === null ? route('baskets.index', ['pending' => 1], false) : route('baskets.show', $basketId, false);
    }
}
