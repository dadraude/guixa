<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\StockLevelRow;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\ListStock;
use Inertia\Inertia;
use Inertia\Response;

class StockProductController extends Controller
{
    public function index(Actor $actor, ListStock $list): Response
    {
        return Inertia::render('Manage/Stock/Index', [
            'products' => array_map(StockLevelRow::from(...), $list($actor)),
        ]);
    }
}
