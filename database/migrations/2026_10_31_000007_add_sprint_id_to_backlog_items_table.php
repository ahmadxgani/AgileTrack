<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backlog_items', function (Blueprint $table) {
            // Restrict rather than null-on-delete: deleting a sprint must
            // not silently return its historical work to the backlog pool.
            $table->foreignId('sprint_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backlog_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sprint_id');
        });
    }
};
