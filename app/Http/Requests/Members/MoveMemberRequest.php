<?php

namespace App\Http\Requests\Members;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * Members can only move to an active household.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'household_id' => [
                'required',
                'uuid',
                Rule::exists(Household::class, 'id')->where('is_active', true),
            ],
        ];
    }
}
