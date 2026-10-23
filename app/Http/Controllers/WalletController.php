<?php

namespace App\Http\Controllers;

use App\Http\WalletStatementRow;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\GetWalletStatement;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    /** A member's own household wallet (R31). */
    public function show(Actor $actor, GetWalletStatement $getWalletStatement): Response
    {
        abort_if($actor->householdId === null, 404);

        return Inertia::render('Wallet/Statement', [
            'wallet' => WalletStatementRow::from($getWalletStatement($actor, $actor->householdId)),
            'household' => null,
        ]);
    }
}
