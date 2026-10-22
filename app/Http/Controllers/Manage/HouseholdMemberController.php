<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\AddMember;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HouseholdMemberController extends Controller
{
    public function store(string $household, Request $request, Actor $actor, AddMember $addMember): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $addMember($actor, $household, $data['name'], $data['email']);

        return back();
    }
}
