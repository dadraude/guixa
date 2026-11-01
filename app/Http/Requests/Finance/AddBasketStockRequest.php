<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class AddBasketStockRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['stock_product_id' => ['required', 'uuid'], 'stock_quantity' => ['required', 'string', 'max:20']];
    }
}
