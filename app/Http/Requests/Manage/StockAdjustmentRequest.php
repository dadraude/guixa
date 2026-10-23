<?php

namespace App\Http\Requests\Manage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    /** Authorization lives in the use case and the `role` middleware. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'string', 'regex:/^-?\d{1,7}(?:[.,]\d{1,3})?$/'],
            'note' => ['required', 'string', 'max:500'],
            'cycle_id' => ['nullable', 'uuid', Rule::exists('cycles', 'id')],
        ];
    }

    /** The cycle the adjustment refers to, if any (R27). */
    public function cycleId(): ?string
    {
        return $this->filled('cycle_id') ? $this->string('cycle_id')->toString() : null;
    }

    public function quantity(): string
    {
        return str_replace(',', '.', trim($this->string('quantity')->toString()));
    }
}
