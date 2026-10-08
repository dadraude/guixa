<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the member is validated here; being active and not yet an administrator
 * is checked by AddAdministratorProfile under a lock, so there is one rule.
 */
class StoreAdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-administrators') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'uuid', 'exists:members,id'],
        ];
    }
}
