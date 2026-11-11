<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function dashboardQueries(User $user): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($user)->get('/dashboard')->assertOk();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

it('reads the reception groups with a query count that grows by a few queries per added producer, not per order line (performance)', function () {
    $this->seed(DemoSeeder::class);
    $this->withoutVite();
    $manager = User::query()->where('email', 'gestora@demo.guixa.test')->firstOrFail();
    dashboardQueries($manager);
    $demo = dashboardQueries($manager);

    $cycle = DB::table('cycles')->whereNull('settled_at')->orderBy('pickup_date')->value('id');
    $producers = 4;
    for ($i = 0; $i < $producers; $i++) {
        $id = (string) Str::uuid7();
        DB::table('producers')->insert(['id' => $id, 'name' => "Extra {$i}", 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $id, 'participating' => true, 'closes_at' => now()->subHour(), 'sent_at' => now()]);
    }
    $grown = dashboardQueries($manager);

    expect($grown - $demo)->toBeLessThanOrEqual($producers * 4);
});
