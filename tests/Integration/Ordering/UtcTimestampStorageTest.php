<?php

namespace Tests\Integration\Ordering;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCycleRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentOrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** ADR 0002 rule 5: timestamps are stored as UTC and read in the cooperative's timezone at the edge. */
final class UtcTimestampStorageTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCER = '00000000-0000-7000-8000-0000000000b1';

    private string $processTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processTimezone = date_default_timezone_get();
        ProducerModel::query()->create([
            'id' => self::PRODUCER, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 2, 'cutoff_time' => '00:30',
        ]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->processTimezone);
        $this->setDatabaseSessionTimezone('DEFAULT');
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** Only PostgreSQL has a session timezone; SQLite stores the text as given. */
    private function setDatabaseSessionTimezone(string $zoneSql): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SET TIME ZONE {$zoneSql}");
    }

    private function madrid(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('Europe/Madrid'));
    }

    /** The cutoff 2026-11-05 00:30 Madrid is on another calendar day in UTC (2026-11-04 23:30). */
    private function storedCycleWithMidnightCutoff(): string
    {
        $repository = new EloquentCycleRepository;
        $cycle = Cycle::open($repository->nextId(), $this->madrid('2026-11-07'), PickupWeekday::of(6), $this->madrid('2026-10-22 10:00'));
        $cycle->addParticipant(self::PRODUCER, CutoffRule::of(2, '00:30'), $this->madrid('2026-10-22 10:00'));
        $repository->add($cycle);

        return $cycle->id;
    }

    #[Test]
    public function a_cutoff_is_stored_as_utc_text_whatever_offset_the_domain_used(): void
    {
        $this->storedCycleWithMidnightCutoff();

        $stored = DB::table('cycle_producers')->value('closes_at');

        $this->assertStringStartsWith('2026-11-04 23:30:00', $stored);
        $this->assertStringNotContainsString('+', $stored);
    }

    #[Test]
    public function the_cutoff_boundary_holds_when_the_process_and_the_database_session_run_in_madrid(): void
    {
        date_default_timezone_set('Europe/Madrid');
        $this->setDatabaseSessionTimezone("'Europe/Madrid'");
        $cycleId = $this->storedCycleWithMidnightCutoff();

        $cycle = (new EloquentCycleRepository)->get($cycleId);

        $this->assertTrue($cycle->isOpenFor(self::PRODUCER, $this->madrid('2026-11-05 00:29:59')));
        $this->assertFalse($cycle->isOpenFor(self::PRODUCER, $this->madrid('2026-11-05 00:30:00')));
    }

    #[Test]
    public function timestamps_written_with_the_current_time_are_utc_even_when_the_process_runs_in_madrid(): void
    {
        date_default_timezone_set('Europe/Madrid');
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-11-05 00:30:00', 'Europe/Madrid'));
        $cycleId = $this->storedCycleWithMidnightCutoff();
        $householdId = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $householdId, 'name' => 'H']);
        DB::table('orders')->insert(['id' => (string) Str::uuid7(), 'cycle_id' => $cycleId, 'household_id' => $householdId]);

        (new EloquentOrderRepository)->archiveOfCycle($cycleId);

        $this->assertStringStartsWith('2026-11-04 23:30:00', DB::table('orders')->value('cancelled_at'));
    }
}
