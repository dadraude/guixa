<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class CreateBasketRequest extends FormRequest
{
    public function rules(): array
    {
        return ['household_id' => ['required', 'uuid']];
    }
}
