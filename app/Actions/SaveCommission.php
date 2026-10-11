<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commission;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or renames a commission. The request already rejects a taken name, but two requests
 * can pass that check together; the case-insensitive unique index then decides, and the loser
 * gets the same validation error instead of a server error.
 */
class SaveCommission
{
    /**
     * @param  Commission|null  $commission  The one to rename; null creates a new one.
     *
     * @throws ValidationException when another commission already has the name, ignoring case
     */
    public function handle(string $name, ?Commission $commission = null): Commission
    {
        $commission ??= new Commission;

        try {
            DB::transaction(fn () => $commission->fill(['name' => $name])->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => trans('validation.unique', ['attribute' => trans('validation.attributes.name')])]);
        }

        return $commission;
    }
}
