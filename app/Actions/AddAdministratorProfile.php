<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Administrator;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds the administrator profile to an existing active member (ADR 0008). It
 * sends no email: the person already has a user and a member profile. The
 * member row is locked so a deactivation or a second add cannot slip in
 * between the checks and the insert.
 */
class AddAdministratorProfile
{
    /**
     * @throws ValidationException when the member is inactive or already an administrator
     */
    public function handle(Member $member): Administrator
    {
        try {
            return DB::transaction(fn () => $this->add($member));
        } catch (UniqueConstraintViolationException) {
            throw $this->memberError(__('This member is already an administrator.'));
        }
    }

    private function add(Member $member): Administrator
    {
        $locked = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->is_active) {
            throw $this->memberError(__('Only active members can become administrators.'));
        }

        if ($locked->user->administrator()->exists()) {
            throw $this->memberError(__('This member is already an administrator.'));
        }

        return $locked->user->administrator()->create();
    }

    private function memberError(string $message): ValidationException
    {
        return ValidationException::withMessages(['member_id' => $message]);
    }
}
