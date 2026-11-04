<?php

namespace App\Http\Controllers\Finance;

use App\Http\BasketHouseholdRow;
use App\Http\BasketRow;
use App\Http\BasketSummaryRow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateBasketRequest;
use App\Http\ReturnItemRow;
use App\Http\StockLevelRow;
use Guixa\Sales\Application\CancelDraftBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\GetBasket;
use Guixa\Sales\Application\GetBasketBalance;
use Guixa\Sales\Application\ListActiveHouseholds;
use Guixa\Sales\Application\ListBaskets;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListActiveStock;
use Guixa\Stock\Application\ListReturnItems;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class BasketController extends Controller
{
    public function index(Actor $actor, ListBaskets $list): Response
    {
        return Inertia::render('Finance/Baskets/Index', ['baskets' => array_map(BasketSummaryRow::from(...), $list($actor))]);
    }

    public function create(Actor $actor, ListActiveHouseholds $households): Response
    {
        return Inertia::render('Finance/Baskets/Create', ['households' => array_map(BasketHouseholdRow::from(...), $households($actor))]);
    }

    public function store(CreateBasketRequest $request, Actor $actor, CreateStockBasket $create): RedirectResponse
    {
        $id = $create($actor, $request->string('household_id')->toString());

        return to_route('finance.baskets.show', ['basket' => $id]);
    }

    public function show(string $basket, Actor $actor, GetBasket $get, GetBasketBalance $balance, ListActiveStock $stock,
        ListReturnItems $returns): Response
    {
        try {
            $record = $get($actor, $basket);
        } catch (BasketNotFound) {
            abort(404);
        }

        return Inertia::render('Finance/Baskets/Show', [
            'basket' => BasketRow::from($record, $balance($actor, $record->householdId)),
            'stockProducts' => $record->can->edit
                ? array_map(StockLevelRow::from(...), $stock($actor))
                : [],
            'returnItems' => $record->can->edit
                ? array_map(ReturnItemRow::from(...), $returns($actor))
                : [],
        ]);
    }

    public function cancel(string $basket, Actor $actor, CancelDraftBasket $cancel): RedirectResponse
    {
        $cancel($actor, $basket);

        return to_route('finance.baskets.show', ['basket' => $basket]);
    }
}
