<?php

namespace App\Http\Controllers\Households;

use App\Actions\CreateHousehold;
use App\Actions\DeactivateHousehold;
use App\Actions\ReactivateHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Households\SaveHouseholdRequest;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdController extends Controller
{
    public function index(Request $request): Response
    {
        $households = Household::query()
            ->orderBy('number')
            ->get()
            ->map(fn (Household $household) => [
                'id' => $household->id,
                'number' => $household->number,
                'name' => $household->name,
                'is_active' => $household->is_active,
            ]);

        return Inertia::render('households/Index', [
            'households' => $households,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(SaveHouseholdRequest $request, CreateHousehold $createHousehold): RedirectResponse
    {
        $createHousehold->handle($request->validated('name'));

        return to_route('households.index');
    }

    public function update(SaveHouseholdRequest $request, Household $household): RedirectResponse
    {
        $household->update($request->validated());

        return to_route('households.index');
    }

    public function deactivate(Household $household, DeactivateHousehold $deactivateHousehold): RedirectResponse
    {
        $deactivateHousehold->handle($household);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Household deactivated.')]);

        return to_route('households.index');
    }

    public function reactivate(Household $household, ReactivateHousehold $reactivateHousehold): RedirectResponse
    {
        $reactivateHousehold->handle($household);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Household reactivated. Its members stay inactive until you reactivate them.')]);

        return to_route('households.index');
    }
}
