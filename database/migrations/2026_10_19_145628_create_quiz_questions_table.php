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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 80);
            $table->text('prompt');
            $table->json('options');
            $table->unsignedTinyInteger('correct_option');
            $table->text('explanation');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['quiz_id', 'position']);
            $table->index(['quiz_id', 'topic']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
