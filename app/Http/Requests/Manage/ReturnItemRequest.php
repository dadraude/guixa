<?php

namespace App\Http\Requests\Manage;

use App\Http\EuroAmount;
use Illuminate\Foundation\Http\FormRequest;

class ReturnItemRequest extends FormRequest
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
            'amount' => ['required', 'string', 'regex:'.EuroAmount::PATTERN],
        ];
    }

    public function amountCents(): int
    {
        return EuroAmount::toCents($this->string('amount')->toString());
    }
}
