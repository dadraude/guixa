<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchProfileRequest extends FormRequest
{
    /**
     * Only a profile the person actually has can be chosen.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(#[CurrentUser] User $user): array
    {
        $available = app(ActiveProfile::class)->available($user);

        return [
            'profile' => ['required', Rule::in(array_map(fn (Profile $profile) => $profile->value, $available))],
        ];
    }

    public function profile(): Profile
    {
        return Profile::from($this->string('profile')->toString());
    }
}
