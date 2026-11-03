<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class SetWeeklyQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['quantity' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,3})?$/']];
    }
}
