<?php

namespace App\Http\Requests\Auth;

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchProfileRequest extends FormRequest
{
    /**
     * Only a profile the person actually has can be chosen.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $available = app(ActiveProfile::class)->available($this->user());

        return [
            'profile' => ['required', Rule::in(array_map(fn (Profile $profile) => $profile->value, $available))],
        ];
    }

    public function profile(): Profile
    {
        return Profile::from($this->validated('profile'));
    }
}
