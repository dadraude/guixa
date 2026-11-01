<?php

namespace App\Http\Controllers\Member;

use App\Http\BasketRow;
use App\Http\BasketSummaryRow;
use App\Http\Controllers\Controller;
use Guixa\Sales\Application\GetMemberBasket;
use Guixa\Sales\Application\ListMemberBaskets;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\GetWalletStatement;
use Inertia\Inertia;
use Inertia\Response;

final class BasketController extends Controller
{
    public function index(Actor $actor, ListMemberBaskets $list): Response
    {
        return Inertia::render('Member/Baskets/Index', [
            'baskets' => array_map(BasketSummaryRow::from(...), $list($actor)),
        ]);
    }

    public function show(string $basket, Actor $actor, GetMemberBasket $get, GetWalletStatement $statement): Response
    {
        try {
            $record = $get($actor, $basket);
        } catch (BasketNotFound) {
            abort(404);
        }

        return Inertia::render('Member/Baskets/Show', [
            'basket' => BasketRow::from($record, $statement($actor, $record->householdId)->balanceCents),
        ]);
    }
}
