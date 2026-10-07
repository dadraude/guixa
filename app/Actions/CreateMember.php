<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\WelcomeMail;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Creates a member by hand (ADR 0008). Every user has a member profile, the
 * owner included, so any email that already belongs to a user is rejected and
 * no profile is ever attached to an existing user.
 *
 * The household is locked like "change household" does, so it cannot be
 * deactivated between the check and the insert. Anything that can change
 * between the form validation and the transaction is checked again here, and
 * a lost race on the email ends as a validation error.
 */
class CreateMember
{
    /**
     * @param  list<string>  $commissionIds
     *
     * @throws ValidationException
     */
    public function handle(string $name, string $email, ?string $phone, ?string $householdId, array $commissionIds): Member
    {
        try {
            return DB::transaction(fn () => $this->create($name, $email, $phone, $householdId, $commissionIds));
        } catch (UniqueConstraintViolationException) {
            throw $this->emailError(__('This email already belongs to a member.'));
        }
    }

    /**
     * @param  list<string>  $commissionIds
     */
    private function create(string $name, string $email, ?string $phone, ?string $householdId, array $commissionIds): Member
    {
        $this->assertHouseholdIsActive($householdId);
        $commissionIds = array_values(array_unique($commissionIds));
        $this->assertCommissionsExist($commissionIds);

        $user = User::query()->where('email', mb_strtolower($email))->lockForUpdate()->first();

        if ($user !== null) {
            throw $this->emailError($user->member?->is_active === false
                ? __('This member is inactive: reactivate them instead of creating a new one.')
                : __('This email already belongs to a member.'));
        }

        $user = User::create(['name' => $name, 'email' => $email]);

        $member = $user->member()->create(['phone' => $phone, 'household_id' => $householdId, 'is_active' => true]);
        $member->commissions()->attach($commissionIds);

        // Queued after commit (ShouldQueueAfterCommit), so it is safe inside the transaction.
        Mail::to($user)->queue(new WelcomeMail(route('login'), $name));

        return $member;
    }

    private function assertHouseholdIsActive(?string $householdId): void
    {
        if ($householdId === null) {
            return;
        }

        $household = Household::query()->whereKey($householdId)->lockForUpdate()->first();

        if ($household?->is_active === true) {
            return;
        }

        throw ValidationException::withMessages(['household_id' => __('Choose an active household.')]);
    }

    /**
     * @param  list<string>  $commissionIds  without duplicates
     */
    private function assertCommissionsExist(array $commissionIds): void
    {
        // Locked so a commission cannot be deleted between this check and the attach.
        $existing = Commission::query()->whereKey($commissionIds)->lockForUpdate()->get(['id']);

        if (count($existing) === count($commissionIds)) {
            return;
        }

        throw ValidationException::withMessages(['commission_ids' => __('Choose existing commissions.')]);
    }

    private function emailError(string $message): ValidationException
    {
        return ValidationException::withMessages(['email' => $message]);
    }
}
