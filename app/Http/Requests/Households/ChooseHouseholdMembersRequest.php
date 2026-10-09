<?php

declare(strict_types=1);

namespace App\Http\Requests\Households;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deactivating or reactivating a household asks whether its members follow. Nothing
 * is preselected, so the choice must be explicit whenever there are members it would
 * affect: the active ones when deactivating, the inactive ones when reactivating.
 */
class ChooseHouseholdMembersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'with_members' => [Rule::requiredIf($this->hasMembersToAffect(...)), 'boolean'],
        ];
    }

    public function withMembers(): bool
    {
        return $this->boolean('with_members');
    }

    private function hasMembersToAffect(): bool
    {
        $household = $this->route('household');

        return $household instanceof Household
            && $household->members()->where('is_active', $household->is_active)->exists();
    }
}
