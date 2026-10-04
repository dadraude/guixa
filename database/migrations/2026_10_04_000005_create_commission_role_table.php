<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles are an enumeration in code, so only the assignment is stored.
     */
    public function up(): void
    {
        Schema::create('commission_role', function (Blueprint $table) {
            $table->foreignUuid('commission_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->primary(['commission_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_role');
    }
};
