<?php

declare(strict_types=1);

namespace App\Http\Requests\Commissions;

use App\Models\Commission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $commission = $this->route('commission');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Commission::class, 'name')->ignore($commission instanceof Commission ? $commission->id : null),
            ],
        ];
    }
}
