<?php

namespace App\Http\Requests\Manage;

use App\Http\EuroAmount;
use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
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
            'amount' => ['required', 'string', 'regex:'.EuroAmount::PATTERN],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function amountCents(): int
    {
        return EuroAmount::toCents($this->string('amount')->toString());
    }
}
