<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Sales\Domain\AccountNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SalesAccountNamesPortTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_names_the_authors_membership_stores_deactivated_accounts_included(): void
    {
        $active = User::factory()->create(['name' => 'Maria Manager']);
        $deactivated = User::factory()->create(['name' => 'Dan Departed']);
        DB::table('users')->where('id', $deactivated->id)->update(['deactivated_at' => now()]);

        $names = $this->app->make(AccountNames::class)->forAccounts([$active->id, $deactivated->id, '00000000-0000-7000-8000-0000000000ff']);

        $this->assertEquals([$active->id => 'Maria Manager', $deactivated->id => 'Dan Departed'], $names);
    }
}
