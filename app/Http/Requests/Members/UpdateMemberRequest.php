<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Concerns\ProfileValidationRules;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateMemberRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * Compare emails in lower case, as they are stored.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');

        return $this->profileRules($member->user_id);
    }
}
