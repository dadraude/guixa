<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Models\Commission;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
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
     * Whether the email is already taken is decided by CreateMember, under a
     * lock, so a double submit gives the same error as a plain duplicate.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'household_id' => [
                'nullable',
                'uuid',
                Rule::exists(Household::class, 'id')->where('is_active', true),
            ],
            'commission_ids' => ['nullable', 'array'],
            'commission_ids.*' => ['uuid', 'distinct', Rule::exists(Commission::class, 'id')],
        ];
    }
}
