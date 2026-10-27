<?php

namespace App\Http\Controllers;

use Guixa\Catalog\Application\ListProducers;
use Guixa\Membership\Application\ListAccounts;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Membership\Application\ReadHouseholdBalance;
use Guixa\Ordering\Application\ListCycles;
use Guixa\Ordering\Application\ListMemberCycles;
use Guixa\Ordering\Application\PendingPickupData;
use Guixa\Ordering\Application\ReadPendingPickups;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(
        Actor $actor,
        ListMemberCycles $listMemberCycles,
        ListCycles $listCycles,
        ListHouseholds $listHouseholds,
        ListAccounts $listAccounts,
        ListProducers $listProducers,
        ReadHouseholdBalance $readHouseholdBalance,
        ReadPendingPickups $readPendingPickups,
    ): Response {
        $roles = $actor->roleNames();
        $isManager = in_array('manager', $roles, true);
        $isMember = in_array('member', $roles, true);

        $hasHousehold = $isMember && $actor->householdId !== null;
        $memberCycles = $hasHousehold ? $listMemberCycles($actor) : [];
        $openCycles = array_filter(
            $memberCycles,
            fn ($c) => $c->open && ! $c->cancelled,
        );
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

        $pendingPickups = [];
        if ($hasHousehold) {
            $pendingPickups = array_map(
                fn (PendingPickupData $p) => [
                    'cycleId' => $p->cycleId,
                    'pickupDate' => $p->pickupDate,
                ],
                $readPendingPickups($actor->householdId),
            );
        }

        $managerSummary = null;
        if ($isManager) {
            $cycles = $listCycles($actor);
            $households = $listHouseholds($actor);
            $accounts = $listAccounts($actor);
            $producers = $listProducers($actor);

            $managerSummary = [
                'cycles' => count($cycles),
                'households' => count($households),
                'accounts' => count($accounts),
                'producers' => count($producers),
            ];
        }

        return Inertia::render('Dashboard', [
            'roles' => $roles,
            'isManager' => $isManager,
            'isMember' => $isMember,
            'nextPickup' => $nextPickup,
            'openCycles' => count($openCycles),
            'balance' => $balance,
            'pendingPickups' => $pendingPickups,
            'managerSummary' => $managerSummary,
        ]);
    }
}
