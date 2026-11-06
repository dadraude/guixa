<?php

namespace Tests\Integration\Ordering;

use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Infrastructure\Persistence\EloquentCooperativeSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\CooperativeSettingsContract;
use Tests\TestCase;

final class EloquentCooperativeSettingsTest extends TestCase
{
    use CooperativeSettingsContract;
    use RefreshDatabase;

    protected function settings(): CooperativeSettings
    {
        return new EloquentCooperativeSettings;
    }

    #[Test]
    public function the_database_keeps_a_single_settings_row(): void
    {
        $this->assertThrows(fn () => DB::table('cooperative_settings')->insert(['id' => 2, 'pickup_weekday' => 6]), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_a_weekday_outside_one_to_seven(): void
    {
        foreach ([0, 8] as $weekday) {
            $this->assertThrows(fn () => DB::table('cooperative_settings')->where('id', 1)->update(['pickup_weekday' => $weekday]), QueryException::class);
        }
    }
}
