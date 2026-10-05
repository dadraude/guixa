<?php

declare(strict_types=1);

namespace App\Http\Controllers\Households;

use App\Actions\CancelInvitation;
use App\Actions\ResendInvitation;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendingInvitationController extends Controller
{
    public function index(Request $request): Response
    {
        $invitations = Invitation::query()
            ->open()
            ->with('household')
            ->orderBy('expires_at')
            ->get()
            ->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'household' => $invitation->household->name,
                'sent_at' => $invitation->updated_at?->toIso8601String(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'is_expired' => $invitation->expires_at->isPast(),
            ]);

        return Inertia::render('households/Invitations', [
            'invitations' => $invitations,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function resend(Invitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        $this->abortUnlessOpen($invitation);

        $resendInvitation->handle($invitation);

        return to_route('households.invitations.index')->with('status', __('Invitation resent.'));
    }

    public function cancel(Invitation $invitation, CancelInvitation $cancelInvitation): RedirectResponse
    {
        $this->abortUnlessOpen($invitation);

        $cancelInvitation->handle($invitation);

        return to_route('households.invitations.index')->with('status', __('Invitation cancelled.'));
    }

    /**
     * Accepted and cancelled invitations are gone as far as managing goes.
     */
    private function abortUnlessOpen(Invitation $invitation): void
    {
        abort_unless($invitation->isOpen(), 404);
    }
}
