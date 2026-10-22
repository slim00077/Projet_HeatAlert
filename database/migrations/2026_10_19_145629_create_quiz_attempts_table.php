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
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('quiz_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('correct_answers');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('points_earned');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
