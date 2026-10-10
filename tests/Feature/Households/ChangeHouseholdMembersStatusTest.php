<?php

declare(strict_types=1);

use App\Actions\ChangeHouseholdMembersStatus;
use App\Models\Household;
use Illuminate\Support\Facades\DB;

it('refuses to run outside the transaction that locked the household', function () {
    $household = Household::factory()->create();

    // RefreshDatabase wraps every test in a transaction, so leave it to reach level zero
    // and open it again afterwards so the framework can still roll back at teardown.
    DB::rollBack();

    try {
        expect(fn () => app(ChangeHouseholdMembersStatus::class)->handle($household, true, fn () => true, fn () => null))
            ->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});
