<?php

namespace App\Http\Requests\Households;

use App\Models\Member;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class InviteToHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * Emails are stored in lower case, so compare them in lower case too.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', $this->notAMemberYet()],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function notAMemberYet(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $alreadyMember = Member::query()->whereHas('user', fn ($users) => $users->where('email', $value))->exists();

            if ($alreadyMember) {
                $fail(__('This email already belongs to a member.'));
            }
        };
    }
}
