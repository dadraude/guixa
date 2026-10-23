<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\ProducerRow;
use Guixa\Catalog\Application\CreateProducer;
use Guixa\Catalog\Application\ListProducers;
use Guixa\Membership\Application\ListProducerAccounts;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProducerController extends Controller
{
    public function index(Actor $actor, ListProducers $listProducers, ListProducerAccounts $listProducerAccounts): Response
    {
        $accounts = $listProducerAccounts($actor);

        return Inertia::render('Manage/Producers/Index', [
            'producers' => array_map(
                fn ($producer) => ProducerRow::from($producer, $accounts[$producer->id] ?? null),
                $listProducers($actor),
            ),
        ]);
    }

    public function store(Request $request, Actor $actor, CreateProducer $createProducer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cutoff_days_before' => ['required', 'integer'],
            'cutoff_time' => ['required', 'string'],
        ]);

        $createProducer($actor, $data['name'], (int) $data['cutoff_days_before'], $data['cutoff_time']);

        return to_route('manage.producers.index');
    }
}
