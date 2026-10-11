<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\AddAdministratorProfile;
use App\Actions\RemoveAdministratorProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdministratorRequest;
use App\Models\Administrator;
use App\Models\Member;
use App\Support\CatalanCollation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    public function index(): Response
    {
        $administrators = CatalanCollation::orderByColumn(
            Administrator::query()
                ->select('administrators.*')
                ->join('users', 'users.id', '=', 'administrators.user_id')
                ->with('user.member'),
            'users.name',
        )->orderBy('administrators.id')->get()
            ->map(fn (Administrator $administrator) => [
                'id' => $administrator->id,
                'name' => $administrator->user->name,
                'email' => $administrator->user->email,
                'is_owner' => $administrator->is_owner,
                // Informational only: an inactive member keeps the profile but cannot sign in.
                'is_active' => $administrator->user->isActive(),
            ]);

        // The picker: active members who are not administrators yet.
        $candidates = CatalanCollation::orderByColumn(
            Member::query()
                ->select('members.*')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->with('user')
                ->where('members.is_active', true)
                ->whereDoesntHave('user.administrator'),
            'users.name',
        )->orderBy('members.id')->get()
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
