<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_movements', function (Blueprint $table) {
            $table->foreignUuid('source_movement_id')->nullable()->constrained('wallet_movements');
        });
        DB::statement("ALTER TABLE wallet_movements ADD CONSTRAINT wallet_correction_source_shape CHECK ((type = 'correction') OR source_movement_id IS NULL)");
        DB::statement('CREATE INDEX wallet_movements_source_id ON wallet_movements (source_movement_id) WHERE source_movement_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS wallet_movements_source_id');
        DB::statement('ALTER TABLE wallet_movements DROP CONSTRAINT wallet_correction_source_shape');
        Schema::table('wallet_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_movement_id'));
    }
};
