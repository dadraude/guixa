<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\AddAdministratorProfile;
use App\Actions\RemoveAdministratorProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdministratorRequest;
use App\Models\Administrator;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    public function index(): Response
    {
        $administrators = Administrator::query()
            ->with('user.member')
            ->oldest()
            ->get()
            ->map(fn (Administrator $administrator) => [
                'id' => $administrator->id,
                'name' => $administrator->user->name,
                'email' => $administrator->user->email,
                'is_owner' => $administrator->is_owner,
                // Informational only: an inactive member keeps the profile but cannot sign in.
                'is_active' => $administrator->user->member?->is_active !== false,
            ]);

        // The picker: active members who are not administrators yet.
        $candidates = Member::query()
            ->with('user')
            ->where('is_active', true)
            ->whereDoesntHave('user.administrator')
            ->get()
            ->sortBy(fn (Member $member) => mb_strtolower($member->user->name))
            ->values()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
            ]);

        return Inertia::render('admin/Administrators', [
            'administrators' => $administrators,
            'candidates' => $candidates,
        ]);
    }

    public function store(StoreAdministratorRequest $request, AddAdministratorProfile $addAdministratorProfile): RedirectResponse
    {
        $member = Member::query()->findOrFail($request->safe()->string('member_id')->toString());

        $addAdministratorProfile->handle($member);

        return to_route('admin.administrators.index');
    }

    public function destroy(Administrator $administrator, RemoveAdministratorProfile $removeAdministratorProfile): RedirectResponse
    {
        $removeAdministratorProfile->handle($administrator);

        return to_route('admin.administrators.index');
    }
}
