<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\WalletHouseholdRow;
use App\Http\WalletStatementRow;
use Guixa\Membership\Application\GetWalletHousehold;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\GetWalletStatement;
use Inertia\Inertia;
use Inertia\Response;

final class HouseholdWalletController extends Controller
{
    public function show(string $household, Actor $actor, GetWalletHousehold $getHousehold, GetWalletStatement $getStatement): Response
    {
        try {
            $name = $getHousehold($actor, $household);
        } catch (HouseholdNotFound) {
            abort(404);
        }

        return Inertia::render('Wallet/Statement', [
            'wallet' => WalletStatementRow::from($getStatement($actor, $household)),
            'household' => new WalletHouseholdRow($name->id, $name->name),
            'canManageReceipts' => false,
        ]);
    }
}
