<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\ReturnItemRequest;
use App\Http\ReturnItemRow;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\CreateReturnItem;
use Guixa\Stock\Application\DeactivateReturnItem;
use Guixa\Stock\Application\ListReturnItems;
use Guixa\Stock\Application\ReactivateReturnItem;
use Guixa\Stock\Application\UpdateReturnItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReturnItemController extends Controller
{
    public function index(Actor $actor, ListReturnItems $list): Response
    {
        return Inertia::render('Manage/ReturnItems/Index', [
            'items' => array_map(ReturnItemRow::from(...), $list($actor)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Manage/ReturnItems/Create');
    }

    public function store(ReturnItemRequest $request, Actor $actor, CreateReturnItem $create): RedirectResponse
    {
        $create($actor, $request->string('name')->toString(), $request->amountCents());

        return to_route('manage.return-items.index');
    }

    public function edit(string $returnItem, Actor $actor, ListReturnItems $list): Response
    {
        $item = collect($list($actor))->firstWhere('id', $returnItem);
        abort_if($item === null, 404);

        return Inertia::render('Manage/ReturnItems/Edit', ['item' => ReturnItemRow::from($item)]);
    }

    public function update(string $returnItem, ReturnItemRequest $request, Actor $actor, UpdateReturnItem $update): RedirectResponse
    {
        $update($actor, $returnItem, $request->string('name')->toString(), $request->amountCents());

        return to_route('manage.return-items.index');
    }

    public function destroy(string $returnItem, Actor $actor, DeactivateReturnItem $deactivate): RedirectResponse
    {
        $deactivate($actor, $returnItem);

        return to_route('manage.return-items.index');
    }

    public function reactivate(string $returnItem, Actor $actor, ReactivateReturnItem $reactivate): RedirectResponse
    {
        $reactivate($actor, $returnItem);

        return to_route('manage.return-items.index');
    }
}
