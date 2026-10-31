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
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            // A sprint always runs between two known dates, so both are
            // required and the request rules keep the order straight.
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status')->default('planned');
            $table->timestamps();

            // One active sprint per project is enforced by the controller;
            // this index just makes the lookup cheap.
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
