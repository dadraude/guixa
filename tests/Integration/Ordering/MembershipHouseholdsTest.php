<?php

namespace Tests\Integration\Ordering;

use Guixa\Ordering\Domain\Households;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MembershipHouseholdsTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIVE = '00000000-0000-7000-8000-0000000000e1';

    private const INACTIVE = '00000000-0000-7000-8000-0000000000e2';

    #[Test]
    public function it_answers_the_name_and_active_status_of_the_households_membership_stores(): void
    {
        DB::table('households')->insert(['id' => self::INACTIVE, 'name' => 'Household B', 'deactivated_at' => UtcTimestamp::toStorage(now()), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('households')->insert(['id' => self::ACTIVE, 'name' => 'Household A', 'created_at' => now(), 'updated_at' => now()]);

        $found = $this->app->make(Households::class)->find([self::ACTIVE, self::INACTIVE, '00000000-0000-7000-8000-0000000000ff']);

        // Households::find promises the found ids as keys, not an order; the unknown id is left out.
        $this->assertEqualsCanonicalizing([self::ACTIVE, self::INACTIVE], array_keys($found));
        $this->assertSame(['Household A', true], [$found[self::ACTIVE]->name, $found[self::ACTIVE]->active]);
        $this->assertSame(['Household B', false], [$found[self::INACTIVE]->name, $found[self::INACTIVE]->active]);
    }
}
