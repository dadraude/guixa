<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateAdministrator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdministratorRequest;
use App\Models\Administrator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    public function index(): Response
    {
        $administrators = Administrator::query()
            ->with('user')
            ->oldest()
            ->get()
            ->map(fn (Administrator $administrator) => [
                'id' => $administrator->id,
                'name' => $administrator->user->name,
                'email' => $administrator->user->email,
                'is_owner' => $administrator->is_owner,
                'is_active' => $administrator->is_active,
            ]);

        return Inertia::render('admin/Administrators', ['administrators' => $administrators]);
    }

    public function store(StoreAdministratorRequest $request, CreateAdministrator $createAdministrator): RedirectResponse
    {
        $createAdministrator->handle($request->validated('name'), $request->validated('email'));

        return to_route('admin.administrators.index');
    }

    public function deactivate(Administrator $administrator): RedirectResponse
    {
        abort_if($administrator->is_owner, 403);

        $administrator->update(['is_active' => false]);

        return to_route('admin.administrators.index');
    }

    public function reactivate(Administrator $administrator): RedirectResponse
    {
        abort_if($administrator->is_owner, 403);

        $administrator->update(['is_active' => true]);

        return to_route('admin.administrators.index');
    }
}
