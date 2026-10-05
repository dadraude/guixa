<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use App\Concerns\PasswordValidationRules;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Someone who already has a user keeps their name and password, so only a
     * new person is asked for them.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if ($this->invitationIsForExistingUser()) {
            return [];
        }

        return ['name' => ['required', 'string', 'max:255'], 'password' => $this->passwordRules()];
    }

    private function invitationIsForExistingUser(): bool
    {
        $invitation = Invitation::query()->withToken((string) $this->route('token'))->usable()->first();

        return $invitation?->existingUser() !== null;
    }
}
