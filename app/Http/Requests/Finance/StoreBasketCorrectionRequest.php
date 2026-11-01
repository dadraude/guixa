<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBasketCorrectionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:2000'],
            'correction_id' => ['required', 'uuid'],
        ];
    }
}
