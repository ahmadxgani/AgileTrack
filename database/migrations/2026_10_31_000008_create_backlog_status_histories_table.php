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
        Schema::create('backlog_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backlog_item_id')->constrained()->cascadeOnDelete();
            // Null means the item had no status yet, e.g. an imported row.
            $table->string('from_status')->nullable();
            $table->string('to_status');
            // Kept when the account is deleted: the move still happened.
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            // Append-only log, so the row never changes after it is written.
            $table->timestamp('created_at');

            $table->index(['backlog_item_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlog_status_histories');
    }
};
