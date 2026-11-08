<?php

namespace App\Http\Requests\Manage;

use Guixa\Shared\Application\Actor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GrantStaffRoleRequest extends FormRequest
{
    /**
     * Runs before validation and after the `role` middleware: a role this screen does not manage answers 404
     * whatever the body holds, while other accounts still get 403. The use case enforces the manager check.
     */
    public function authorize(): bool
    {
        if (! Gate::forUser(app(Actor::class))->allows('staff-role', (string) $this->route('staffRole'))) {
            throw new NotFoundHttpException;
        }

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'uuid'],
        ];
    }
}
