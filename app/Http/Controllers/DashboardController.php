<?php

namespace App\Http\Controllers;

use Guixa\Catalog\Application\CountProducers;
use Guixa\Membership\Application\CountAccounts;
use Guixa\Membership\Application\CountHouseholds;
use Guixa\Ordering\Application\CountCycles;
use Guixa\Ordering\Application\CountOpenMemberCycles;
use Guixa\Ordering\Application\ListMemberCycles;
use Guixa\Sales\Application\BasketData;
use Guixa\Sales\Application\ListMemberPendingCollections;
use Guixa\Sales\Application\ListPendingCollections;
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
        ListMemberPendingCollections $memberPending,
        ListPendingCollections $staffPending,
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

        $pendingPickups = [];
        if ($hasHousehold) {
            $pendingPickups = array_map(
                fn (BasketData $basket) => [
                    'basketId' => $basket->id,
                    'validatedAt' => $basket->validatedAt?->setTimezone(new \DateTimeZone(config('guixa.timezone')))->format('Y-m-d'),
                    'weekly' => $basket->weekly,
                    'late' => $basket->late,
                ],
                $memberPending($actor),
            );
        }

        $staffPendingCollections = null;
        if (array_intersect(['manager', 'finance'], $roles) !== []) {
            $staffPendingCollections = ['count' => count($staffPending($actor))];
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
            'pendingPickups' => $pendingPickups,
            'staffPendingCollections' => $staffPendingCollections,
            'managerSummary' => $managerSummary,
        ]);
    }
}
