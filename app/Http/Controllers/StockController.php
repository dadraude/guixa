<?php

namespace App\Http\Controllers;

use App\Http\StockOfferRow;
use Guixa\Sales\Application\ListStockOffers;
use Guixa\Shared\Application\Actor;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Actor $actor, ListStockOffers $offers): Response
    {
        return Inertia::render('Stock/Index', ['products' => array_map(StockOfferRow::from(...), $offers($actor))]);
    }
}
