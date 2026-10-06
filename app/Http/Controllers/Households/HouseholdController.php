<?php

declare(strict_types=1);

namespace App\Http\Controllers\Households;

use App\Actions\CreateHousehold;
use App\Actions\DeactivateHousehold;
use App\Actions\ReactivateHousehold;
use App\Auth\MembershipRoleProtection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Households\SaveHouseholdRequest;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdController extends Controller
{
    public function index(Request $request, MembershipRoleProtection $protection): Response
    {
        $viewer = $request->user() ?? abort(403);
        // Informational only: an active household with nobody active in a
        // commission is flagged so the membership role can encourage it to
        // take part. Nothing is blocked.
        $households = Household::query()
            ->withCommissionMemberFlag()
            ->orderBy('number')
            ->get()
            ->map(fn (Household $household) => [
                'can_deactivate' => $protection->mayDeactivateHousehold($viewer, $household),
                'id' => $household->id,
                'number' => $household->number,
                'name' => $household->name,
                'is_active' => $household->is_active,
                'without_commission' => $household->is_active && ! $household->has_commission_member,
            ]);

        return Inertia::render('households/Index', [
            'households' => $households,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(SaveHouseholdRequest $request, CreateHousehold $createHousehold): RedirectResponse
    {
        $createHousehold->handle($request->safe()->string('name')->toString());

        return to_route('households.index');
    }

    public function update(SaveHouseholdRequest $request, Household $household): RedirectResponse
    {
        $household->update($request->validated());

        return to_route('households.index');
    }

    public function deactivate(Request $request, Household $household, DeactivateHousehold $deactivateHousehold, MembershipRoleProtection $protection): RedirectResponse
    {
        abort_unless($protection->mayDeactivateHousehold($request->user() ?? abort(403), $household), 403);

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
