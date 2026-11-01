<?php

namespace App\Http\Requests\Manage;

use App\Http\EuroAmount;
use Illuminate\Foundation\Http\FormRequest;

class CorrectionRequest extends FormRequest
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
            'note' => ['required', 'string', 'max:500'],
            'cycle_id' => ['prohibited'],
            'source_movement_id' => ['required', 'uuid'],
            'correction_id' => ['required', 'uuid'],
        ];
    }

    public function amountCents(): int
    {
        return EuroAmount::toCents($this->string('amount')->toString());
    }
}
