<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The plain unique index is case-sensitive on PostgreSQL; this one backs the validation
     * rule when two requests race.
     */
    public function up(): void
    {
        DB::statement('create unique index commissions_name_lower_unique on commissions (lower(name))');
    }

    public function down(): void
    {
        DB::statement('drop index commissions_name_lower_unique');
    }
};
