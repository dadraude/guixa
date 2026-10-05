<?php

namespace App\Http\Controllers\Households;

use App\Actions\CreateHousehold;
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
        // Informational only: an active household with nobody active in a
        // commission is flagged so the membership role can encourage it to
        // take part. Nothing is blocked.
        $households = Household::query()
            ->orderBy('number')
            ->get()
            ->map(fn (Household $household) => [
                'id' => $household->id,
                'number' => $household->number,
                'name' => $household->name,
                'is_active' => $household->is_active,
                'without_commission' => $household->is_active && ! $household->hasMemberInAnyCommission(),
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
}
