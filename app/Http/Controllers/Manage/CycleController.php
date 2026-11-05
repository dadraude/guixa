<?php

namespace App\Http\Controllers\Manage;

use App\Http\ContainerCountRow;
use App\Http\Controllers\Controller;
use App\Http\CycleRow;
use App\Http\CycleSummaryRow;
use App\Http\ProducerOptionRow;
use Guixa\Catalog\Application\ListActiveProducers;
use Guixa\Catalog\Application\ListProducers;
use Guixa\Catalog\Application\ProducerData;
use Guixa\Ordering\Application\CountPendingCollectionsByCycle;
use Guixa\Ordering\Application\GetContainerCount;
use Guixa\Ordering\Application\GetCycleSummary;
use Guixa\Ordering\Application\GetPickupWeekday;
use Guixa\Ordering\Application\ListCycles;
use Guixa\Ordering\Application\OpenCycle;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CycleController extends Controller
{
    public function index(Actor $actor, ListCycles $listCycles, ListProducers $listProducers, ListActiveProducers $listActiveProducers, GetPickupWeekday $getPickupWeekday, CountPendingCollectionsByCycle $pendingCollections): Response
    {
        $producers = $listProducers($actor);
        $names = array_column(array_map(fn (ProducerData $producer) => ['id' => $producer->id, 'name' => $producer->name], $producers), 'name', 'id');

        return Inertia::render('Manage/Cycles/Index', [
            'cycles' => array_map(fn ($cycle) => CycleRow::from($cycle, $names), $listCycles($actor)),
            'producers' => array_map(ProducerOptionRow::from(...), $listActiveProducers($actor)),
            'pickupWeekday' => $getPickupWeekday($actor),
            'pendingCollections' => $pendingCollections($actor),
        ]);
    }

    public function show(string $cycle, Actor $actor, GetCycleSummary $getCycleSummary, GetContainerCount $getContainerCount, ListProducers $listProducers, CountPendingCollectionsByCycle $pendingCollections): Response
    {
        try {
            $summary = $getCycleSummary($actor, $cycle);
            $containers = $getContainerCount($actor, $cycle);
        } catch (CycleNotFound) {
            abort(404);
        }

        $names = array_column(array_map(fn (ProducerData $producer) => ['id' => $producer->id, 'name' => $producer->name], $listProducers($actor)), 'name', 'id');

        return Inertia::render('Manage/Cycles/Show', [
            'summary' => CycleSummaryRow::from($summary, $names),
            'containers' => ContainerCountRow::from($containers),
            'pendingCollections' => $this->pendingCollectionsLink($pendingCollections($actor)[$cycle] ?? 0, $cycle),
        ]);
    }

    public function store(Request $request, Actor $actor, OpenCycle $openCycle): RedirectResponse
    {
        $data = $request->validate([
            'pickup_date' => ['required', 'string'],
            'producer_ids' => ['nullable', 'array'],
            'producer_ids.*' => ['uuid'],
            'pickup_weekday' => ['nullable', 'integer', 'between:1,7'],
            'permanent' => ['required_with:pickup_weekday', 'boolean'],
        ]);

        $openCycle(
            $actor,
            $data['pickup_date'],
            array_values($data['producer_ids'] ?? []),
            isset($data['pickup_weekday']) ? (int) $data['pickup_weekday'] : null,
            (bool) ($data['permanent'] ?? false),
        );

        return to_route('manage.cycles.index');
    }

    /** @return array{count: int, href: ?string} */
    private function pendingCollectionsLink(int $count, string $cycle): array
    {
        return [
            'count' => $count,
            'href' => $count === 0 ? null : route('finance.baskets.index', ['pending' => 1, 'cycle' => $cycle], false),
        ];
    }
}
