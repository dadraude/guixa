<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Auth\MagicLinkSignIn;
use App\Concerns\PasswordValidationRules;
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
        $signedInWithMagicLink = MagicLinkSignIn::isRecent($this->session());

        return [
            'current_password' => $signedInWithMagicLink ? ['nullable'] : $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
