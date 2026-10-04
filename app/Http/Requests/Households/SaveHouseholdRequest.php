<?php

namespace App\Http\Requests\Households;

use Illuminate\Foundation\Http\FormRequest;

class SaveHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
