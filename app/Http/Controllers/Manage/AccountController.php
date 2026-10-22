<?php

namespace App\Http\Controllers\Manage;

use App\Http\AccountRow;
use App\Http\Controllers\Controller;
use App\Http\HouseholdSummaryRow;
use Guixa\Membership\Application\ListAccounts;
use Guixa\Membership\Application\ListHouseholds;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Actor $actor, ListAccounts $listAccounts, ListHouseholds $listHouseholds): Response
    {
        return Inertia::render('Manage/Accounts/Index', [
            'accounts' => array_map(AccountRow::from(...), $listAccounts($actor)),
            'households' => array_map(HouseholdSummaryRow::from(...), $listHouseholds($actor)),
        ]);
    }
}
