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
        Schema::create('backlog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('todo');
            // Nullable for the same reason as projects.created_by: account
            // deletion must never be blocked by work that person created.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Items are archived rather than deleted so reviews and sprints
            // added later keep pointing at them.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlog_items');
    }
};
