<?php

namespace App\Http\Requests\Commissions;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCommissionMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * Only active members can be added.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'uuid',
                Rule::exists(Member::class, 'id')->where('is_active', true),
            ],
        ];
    }
}
