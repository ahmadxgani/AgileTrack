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
        Schema::create('sprint_reviews', function (Blueprint $table) {
            $table->id();
            // Every link restricts: a project, sprint or backlog item that a
            // review still points at is never silently emptied. This matches
            // the sprint chain and stays safe under strict PostgreSQL.
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            // A review may cover one sprint; a general project review has none.
            $table->foreignId('sprint_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('feedback')->nullable();
            $table->string('decision')->default('pending');
            // Nullable for the same reason as projects.created_by: deleting an
            // account must not delete the review history it took part in.
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            // Microsecond precision so the stamped moment round-trips
            // exactly, not truncated to the whole second.
            $table->timestamp('decided_at', 6)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // The work a "needs changes" decision asks for. Kept even after the
            // decision moves on, because the item is real work, not a receipt.
            $table->foreignId('follow_up_backlog_item_id')
                ->nullable()
                ->constrained('backlog_items')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprint_reviews');
    }
};
