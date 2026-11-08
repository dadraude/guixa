<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\ListStaffRoleHolders;
use Guixa\Membership\Application\SearchStaffRoleCandidates;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function index(Request $request, Actor $actor, ListStaffRoleHolders $listHolders, SearchStaffRoleCandidates $searchCandidates): Response
    {
        $role = $request->string('role')->toString();
        $query = $request->string('q')->toString();

        return Inertia::render('Manage/Roles/Index', [
            'groups' => array_map(RoleGroupRow::from(...), $listHolders($actor)),
            'candidates' => Inertia::defer(fn () => array_map(RoleCandidateRow::from(...), $searchCandidates($actor, $role, $query))),
        ]);
    }
}
