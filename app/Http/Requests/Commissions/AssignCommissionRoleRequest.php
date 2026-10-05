<?php

declare(strict_types=1);

namespace App\Http\Requests\Commissions;

use App\Auth\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCommissionRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign-roles') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}
