<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baskets', function (Blueprint $table) {
            $table->boolean('late')->default(false);
            $table->dropUnique('baskets_source_order_id_unique');
        });
        DB::statement('CREATE UNIQUE INDEX baskets_one_base_per_source_order ON baskets (source_order_id) WHERE source_order_id IS NOT NULL AND NOT late');
        DB::statement("CREATE UNIQUE INDEX baskets_one_open_late_per_source_order ON baskets (source_order_id) WHERE late AND state IN ('draft', 'nothing_to_collect')");
        DB::statement("ALTER TABLE baskets ADD CONSTRAINT baskets_late_is_weekly CHECK (NOT late OR kind = 'weekly')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE baskets DROP CONSTRAINT baskets_late_is_weekly');
        DB::statement('DROP INDEX baskets_one_open_late_per_source_order');
        DB::statement('DROP INDEX baskets_one_base_per_source_order');
        Schema::table('baskets', function (Blueprint $table) {
            $table->dropColumn('late');
            $table->unique('source_order_id');
        });
    }
};
