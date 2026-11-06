<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_settings', function (Blueprint $table) {
            $table->smallInteger('id')->primary();
            $table->smallInteger('pickup_weekday')->nullable();
        });

        DB::statement('ALTER TABLE cooperative_settings ADD CONSTRAINT cooperative_settings_single_row CHECK (id = 1)');
        DB::statement('ALTER TABLE cooperative_settings ADD CONSTRAINT cooperative_settings_pickup_weekday_range CHECK (pickup_weekday BETWEEN 1 AND 7)');
        // The row starts empty: there is no pickup weekday until a manager sets one (R4c).
        DB::table('cooperative_settings')->insert(['id' => 1, 'pickup_weekday' => null]);

        Schema::create('cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('pickup_date');
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX cycles_pickup_date_live_unique ON cycles (pickup_date) WHERE cancelled_at IS NULL');

        Schema::create('cycle_producers', function (Blueprint $table) {
            $table->foreignUuid('cycle_id')->constrained('cycles');
            $table->foreignUuid('producer_id')->constrained('producers');
            $table->boolean('participating')->default(true);
            $table->timestampTz('closes_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('distributed_at')->nullable();
            $table->primary(['cycle_id', 'producer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_producers');
        Schema::dropIfExists('cycles');
        Schema::dropIfExists('cooperative_settings');
    }
};
