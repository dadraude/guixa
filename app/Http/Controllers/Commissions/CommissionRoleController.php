<?php

namespace App\Http\Controllers\Commissions;

use App\Auth\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commissions\AssignCommissionRoleRequest;
use App\Models\Commission;
use Illuminate\Http\RedirectResponse;

class CommissionRoleController extends Controller
{
    public function store(AssignCommissionRoleRequest $request, Commission $commission): RedirectResponse
    {
        $commission->assignRole(Role::from($request->validated('role')));

        return to_route('commissions.index');
    }

    public function destroy(Commission $commission, Role $role): RedirectResponse
    {
        $commission->removeRole($role);

        return to_route('commissions.index');
    }
}
