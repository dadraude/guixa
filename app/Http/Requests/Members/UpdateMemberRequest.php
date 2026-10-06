<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Auth\EmailChangeLevels;
use App\Concerns\ProfileValidationRules;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateMemberRequest extends FormRequest
{
    use ProfileValidationRules;

    private ?bool $mayChangeEmail = null;

    public function authorize(): bool
    {
        if ($this->user()?->can('manage-membership') !== true) {
            return false;
        }

        // Clients may still send the unchanged email; only an actual change is
        // refused to those below the required level.
        return ! $this->isChangingEmail() || $this->mayChangeEmail();
    }

    private function mayChangeEmail(): bool
    {
        /** @var Member $member */
        $member = $this->route('member');

        return $this->mayChangeEmail ??= app(EmailChangeLevels::class)->canChange($this->user() ?? abort(403), $member);
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

        $rules = $this->profileRules($member->user_id);

        if (! $this->mayChangeEmail()) {
            unset($rules['email']);
        }

        return $rules;
    }

    private function isChangingEmail(): bool
    {
        /** @var Member $member */
        $member = $this->route('member');
        $submitted = $this->input('email');

        return is_string($submitted) && $submitted !== $member->user->email;
    }
}
