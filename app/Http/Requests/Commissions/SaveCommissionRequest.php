<?php

declare(strict_types=1);

namespace App\Http\Requests\Commissions;

use App\Models\Commission;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SaveCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-membership') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $route = $this->route('commission');
        $ignoredId = $route instanceof Commission ? $route->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Compared lowercase in the database; the TrimStrings middleware already trimmed the value.
                function (string $attribute, mixed $value, Closure $fail) use ($ignoredId): void {
                    $taken = Commission::query()
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->when($ignoredId !== null, fn ($query) => $query->whereKeyNot($ignoredId))
                        ->exists();

                    if ($taken) {
                        $fail('validation.unique')->translate();
                    }
                },
            ],
        ];
    }
}
