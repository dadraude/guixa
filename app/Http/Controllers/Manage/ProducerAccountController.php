<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use Guixa\Membership\Application\InviteProducerAccount;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProducerAccountController extends Controller
{
    public function store(string $producer, Request $request, Actor $actor, InviteProducerAccount $inviteProducerAccount): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $inviteProducerAccount($actor, $producer, $data['name'], $data['email']);

        return back();
    }
}
