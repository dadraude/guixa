<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class AddBasketReturnRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['return_item_id' => ['required', 'uuid'], 'return_quantity' => ['required', 'string', 'max:20']];
    }
}
