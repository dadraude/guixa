<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBasketCollectionCorrectionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'correction_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
