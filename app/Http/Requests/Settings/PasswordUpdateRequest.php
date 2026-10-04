<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Someone who signed in with a magic link has probably forgotten it.
        $signedInWithMagicLink = $this->session()->get(SecurityController::MAGIC_LINK_SESSION_KEY, false);

        return [
            'current_password' => $signedInWithMagicLink ? ['nullable'] : $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
