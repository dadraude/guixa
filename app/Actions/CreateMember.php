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
 * Creates a member by hand (ADR 0008). An email that already belongs to a user
 * without a member profile (the owner) gets the profile attached and no
 * welcome email, since that person already knows how to sign in.
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
        $this->assertCommissionsExist($commissionIds);

        $user = User::query()->where('email', mb_strtolower($email))->lockForUpdate()->first();

        if ($user?->member !== null) {
            throw $this->emailError($user->member->is_active
                ? __('This email already belongs to a member.')
                : __('This person is an inactive member: reactivate them instead.'));
        }

        $isNewUser = $user === null;
        $user ??= User::create(['name' => $name, 'email' => $email]);

        $member = $user->member()->create(['phone' => $phone, 'household_id' => $householdId, 'is_active' => true]);
        $member->commissions()->attach($commissionIds);

        if ($isNewUser) {
            // Queued after commit (ShouldQueueAfterCommit), so it is safe inside the transaction.
            Mail::to($user)->queue(new WelcomeMail(route('login'), $name));
        }

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
     * @param  list<string>  $commissionIds
     */
    private function assertCommissionsExist(array $commissionIds): void
    {
        $unique = array_values(array_unique($commissionIds));

        if (Commission::query()->whereKey($unique)->count() === count($unique)) {
            return;
        }

        throw ValidationException::withMessages(['commission_ids' => __('Choose existing commissions.')]);
    }

    private function emailError(string $message): ValidationException
    {
        return ValidationException::withMessages(['email' => $message]);
    }
}
