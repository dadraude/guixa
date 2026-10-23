<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A member is an account linked to a household, not an account holding a role (R35); manager and producer rows stay. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('account_roles')->where('role', 'member')->delete();
    }

    public function down(): void
    {
        $linked = DB::table('users')->whereNotNull('household_id')->pluck('id');

        DB::table('account_roles')->insert($linked->map(fn (string $id) => ['user_id' => $id, 'role' => 'member'])->all());
    }
};
