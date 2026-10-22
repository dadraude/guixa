<?php

namespace App\Http\Requests\Manage;

use Illuminate\Foundation\Http\FormRequest;

class StockEntryRequest extends FormRequest
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
            'quantity' => ['required', 'string', 'regex:/^\d{1,7}(?:[.,]\d{1,3})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function quantity(): string
    {
        return str_replace(',', '.', trim($this->string('quantity')->toString()));
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
