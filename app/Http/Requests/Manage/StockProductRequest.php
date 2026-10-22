<?php

namespace App\Http\Requests\Manage;

use App\Http\EuroAmount;
use Illuminate\Foundation\Http\FormRequest;

class StockProductRequest extends FormRequest
{
    /** Authorization lives in the use case and the `role` middleware. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'string', 'regex:'.EuroAmount::PATTERN],
            'variable' => ['boolean'],
            'containers_per_unit' => ['nullable', 'string', 'regex:/^\d{1,7}(?:[.,]\d{1,3})?$/'],
        ];
    }

    public function priceCents(): int
    {
        return EuroAmount::toCents($this->string('price')->toString());
    }

    public function containersPerUnit(): ?string
    {
        $containers = $this->input('containers_per_unit');

        return is_string($containers) && trim($containers) !== '' ? str_replace(',', '.', trim($containers)) : null;
    }
}
